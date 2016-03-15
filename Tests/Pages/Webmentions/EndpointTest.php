<?php

namespace Tests\Pages\Webmentions;

use Tests\KnownTestCase;
use Idno\Pages\Webmentions\Endpoint;
use Idno\Core\Idno;
use IdnoPlugins\Status\Status;

class EndpointTest extends KnownTestCase
{

    private $toDelete = [];

    function setUp()
    {
        $this->realClient = Idno::site()->http;
        $this->mockClient = $this->getMockBuilder('Idno\Core\HttpClient')
                          ->getMock();
        Idno::site()->http = $this->mockClient;
    }

    function tearDown()
    {
        Idno::site()->http = $this->realClient;
        foreach ($this->toDelete as $obj) {
            //$obj->delete();
        }
    }

    /**
     * Send a simple reply and make sure it gets collected.
     */
    function testReply()
    {
        $status = new Status();
        $status->setOwner($this->user());
        $status->body = "This will be the target of our webmention";
        $status->publish();
        $this->toDelete[] = $status;

        $target = $status->getURL();
        $source = 'http://example.com/2015/this-is-a-reply';
        $sourceContent = <<<EOD
<!DOCTYPE html>
<html>
<body class="h-entry">
  <a class="u-in-reply-to" href="$target">in reply to</a>
  <span class="p-name e-content">This is a reply</span>
  <a class="u-url" href="http://example.com/2015/this-is-a-reply">permalink</a>
  <a class="p-author h-card" href="https://example.com/">Jane Example</a>
</body>
</html>
EOD;
        $this->mockClient
            ->expects($this->once())
            ->method('get')
            ->with($source)
            ->willReturn([
                'response' => 200,
                'content'  => $sourceContent,
            ]);

        $endpoint = Idno::site()->getPageHandler('/webmention');
        $endpoint->setInput('source', $source);
        $endpoint->setInput('target', $target);
        $endpoint->post();

        $this->assertEquals(202, $endpoint->response);

        $status = Status::getByUUID($status->getUUID());

        $this->assertArrayHasKey('reply', $status->getAllAnnotations());
        $this->assertCount(1, $status->getAllAnnotations()['reply']);

        $anno = array_values($status->getAllAnnotations()['reply'])[0];

        $this->assertArrayHasKey('owner_name', $anno);
        $this->assertEquals('Jane Example', $anno['owner_name']);
        $this->assertArrayHasKey('permalink', $anno);
        $this->assertEquals($source, $anno['permalink']);
    }

    /**
     * When we get a webmention where the source is a feed, make
     * sure we handle it gracefully.
     */
    function testWebmentionFromFeed()
    {
        $status = new Status();
        $status->setOwner($this->user());
        $status->body = "This post will be the webmention target";
        $status->publish();
        $this->toDelete[] = $status;

        $target = $status->getURL();
        $source = 'http://example.com/';
        $sourceContent = <<<EOD
<!DOCTYPE html>
<html>
<body>
  <div class="h-entry">
    <a class="p-author h-card" href="https://example.com/">Jane Example</a>
    <span class="p-name e-content">This is just nonsense</span>
    <a class="u-url" href="http://example.com/2015/this-is-just-nonsense">permalink</a>
  </div>
  <div class="h-entry">
    <a class="u-in-reply-to" href="$target">in reply to</a>
    <a class="p-author h-card" href="https://example.com/">Jane Example</a>
    <span class="p-name e-content">This is a reply</span>
    <a class="u-url" href="http://example.com/2015/this-is-a-reply">permalink</a>
  </div>
  <div class="h-entry">
    <a class="p-author h-card" href="https://example.com/">Jane Example</a>
    <span class="p-name e-content">This is probably really serious</span>
    <a class="u-url" href="http://example.com/2015/this-is-probably-really-serious">permalink</a>
  </div>
</body>
</html>
EOD;
        $this->mockClient
            ->expects($this->once())
            ->method('get')
            ->with($source)
            ->willReturn([
                'response' => 200,
                'content'  => $sourceContent,
            ]);

        $endpoint = Idno::site()->getPageHandler('/webmention');
        $endpoint->setInput('source', $source);
        $endpoint->setInput('target', $target);
        $endpoint->post();

        $this->assertEquals(202, $endpoint->response);

        $status = Status::getByUUID($status->getUUID());

        $this->assertEmpty($status->getAllAnnotations());
    }

    /**
     * A particularly knotty case when we get a webmention from *our
     * own* feed. It looks valid because it includes a link, but it's
     * not really.
     */
    function testWebmentionFromOurOwnFeed()
    {
        for ($i = 0 ; $i < 5 ; $i++) {
            $status = new Status();
            $status->setOwner($this->user());
            $status->body = "This post will be the webmention target";
            $status->publish();
            $statuses[] = $status;
            $this->toDelete[] = $status;
        }

        // take one from the middle
        $status = $statuses[2];

        // put the real http client back since all requests are local
        Idno::site()->http = $this->realClient;

        $target = $status->getURL();
        $source = Idno::site()->config()->getDisplayURL();

        error_log("target: $target, source: $source");

        $endpoint = Idno::site()->getPageHandler('/webmention');
        $endpoint->setInput('source', $source);
        $endpoint->setInput('target', $target);
        $endpoint->post();

        $this->assertEquals(202, $endpoint->response);

        $status = Status::getByUUID($status->getUUID());

        $this->assertEmpty($status->getAllAnnotations());
    }


}