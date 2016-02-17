<?php

namespace Tests\API {

    /**
     * Initial api tests.
     */
    class BasicAPITest extends \Tests\KnownTestCase  {

        public function testConnection() {

            $user = \Tests\KnownTestCase::user();

            $result = \Idno\Core\Webservice::get(\Idno\Core\Idno::site()->config()->url . '', [], [
                'Accept: application/json',
            ]);

            $content = json_decode($result['content']);
            $response = $result['response'];

            $this->assertEmpty($result['error']);
            $this->assertNotEmpty($content);
            $this->assertEquals(200, $response);

        }

        public function testAuthenticated() {

            $user = \Tests\KnownTestCase::user();

            $result = \Idno\Core\Webservice::post(\Idno\Core\Idno::site()->config()->url . 'status/edit', [
                'body' => "Making a nice test post via the api",
            ], [
				    'Accept: application/json',
                    'X-KNOWN-USERNAME: ' . $user->handle,
				    'X-KNOWN-SIGNATURE: ' . base64_encode(hash_hmac('sha256', '/status/edit', $user->getAPIkey(), true)),
            ]);

            print_r($result);
            $content = json_decode($result['content']);
            $response = $result['response'];

            $this->assertEmpty($result['error']);
            $this->assertNotEmpty($content);
            $this->assertNotEmpty($content->location);
            $this->assertEquals(200, $response);

        }
    }

}