<?php

namespace Tests\Core {


    use Idno\Core\Idno;
    use Tests\KnownTestCase;
    use Tests\HttpTestClient;
    use IdnoPlugins\Text\Entry;

    class PermalinkStructureTest extends KnownTestCase {

        private function createEntry()
        {
            $rnd = rand(0,9999).'-'.time();
            $entity = new Entry();
            $entity->setOwner($this->user());
            $entity->title = "The Title $rnd";
            $entity->body = 'Unlikely to be present elsewhere in the post template: hamstring baseball duckbill firecracker';
            $entity->save(true);
            return $entity;
        }

        public function testPermalinks()
        {
            $entity = $this->createEntry();
            $base = Idno::site()->config()->getDisplayUrl();
            $year = strftime('%Y', $entity->created);
            $month = strftime('%m', $entity->created);
            $day = strftime('%d', $entity->created);
            $slug = $entity->getSlug();

            // default
            Idno::site()->config()->permalink_structure = null;
            Idno::site()->config()->save();
            $this->assertEquals('/:year/:slug', Idno::site()->config()->getPermalinkStructure());
            $this->assertEquals("$base$year/$slug", $entity->getURL());
            $response = HttpTestClient::get($entity->getURL());
            print_r($response);
            $this->assertEquals(200, $response['response']);
            $this->assertContains('hamstring baseball duckbill firecracker', $response['content']);

            // /year/month/slug
            Idno::site()->config()->permalink_structure = '/:year/:month/:slug';
            Idno::site()->config()->save();
            $this->assertEquals("$base$year/$month/$slug", $entity->getURL());
            $response = HttpTestClient::get($entity->getURL());
            print_r($response);
            $this->assertEquals(200, $response['response']);
            $this->assertContains('hamstring baseball duckbill firecracker', $response['content']);

            $entity->delete();
        }
    }

}
