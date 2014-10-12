<?php

namespace WeavingTheWeb\OwnYourData\Provider;

use GuzzleHttp\Client;

use Silex\Application;

use Silex\ServiceProviderInterface;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class HttpClientProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['http_client'] = $app->share(function () {
            return new Client();
        });
    }

    public function boot(Application $app)
    {
    }
}
