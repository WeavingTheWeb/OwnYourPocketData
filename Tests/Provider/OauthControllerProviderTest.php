<?php

namespace WeavingTheWeb\OwnYourData\Tests\Provider;

use Prophecy\Argument;
use Prophecy\Prophet;

use Silex\WebTestCase;

class OauthControllerProviderTest extends WebTestCase
{
    /**
     * @var \Symfony\Component\HttpKernel\Client
     */
    private $client;

    /**
     * @var Prophet
     */
    private $prophet;

    public function setUp()
    {
        $this->prophet = new Prophet();

        parent::setUp();

        $this->client = $this->createClient();
    }

    public function tearDown()
    {
        $this->prophet->checkPredictions();

        parent::tearDown();
    }

    public function createApplication()
    {
        $app = require __DIR__ . '/../../web/app.php';

        $app['debug'] = true;
        $app['exception_handler']->disable();
        $app['session.test'] = true;

        $httpClientProphecy = $this->prophet->prophesize('\GuzzleHttp\Client');

        $responseProphecy = $this->prophet->prophesize('\GuzzleHttp\Message\Response');
        $responseProphecy->getBody(Argument::cetera())->willReturn(json_encode(
            [
                'code' => 'fake_code',
                'state' => sha1($app['authorization']['secret'])
            ])
        );
        $httpClientProphecy->post(Argument::cetera())->willReturn($responseProphecy->reveal());

        $app['http_client'] = $httpClientProphecy->reveal();

        return $app;
    }

    public function testOauthRequest()
    {
        $this->client->followRedirects(false);
        $this->client->request('GET', '/oauth/request');
        $response = $this->client->getResponse();

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $response,
            'The client should get a response');

        $this->assertEquals(302, $response->getStatusCode(),
            'The response should have an redirect status code ');
    }
} 