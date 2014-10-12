<?php

namespace WeavingTheWeb\OwnYourData\Provider;

use Silex\Application;
use Silex\ControllerProviderInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class OauthControllerProvider implements ControllerProviderInterface
{
    protected $token;

    /**
     * @var Application
     */
    public $app;

    /**
     * @param Application $app
     * @return mixed|\Silex\ControllerCollection
     */
    public function connect(Application $app)
    {
        $this->app = $app;

        $controllers = $app['controllers_factory'];

        /**
         * Request an OAuth token
         */
        $controllers->get('/request', function (Request $request) use ($app) {
            $this->token = $this->requestToken($request);
            $app['session']->set('token', array('request_token' => $this->token));
            $redirectUrl = $this->getAuthorizeApplicationUrl($request, $this->token);

            return $app->redirect($redirectUrl);
        })->bind('oauth.request_token');

        /**
         * Receive the callback from Pocket
         */
        $controllers->get('/callback', function () use ($app) {
            $this->token = $app['session']->get('token')['request_token'];
            $decodedResponseBody = $this->convertRequestTokenIntoAccessToken($app);
            $this->checkState($decodedResponseBody);

            return new Response(sprintf('Access token for "%s" is "%s"',
                $decodedResponseBody['username'], $decodedResponseBody['access_token']));
        })->bind('oauth.callback');

        return $controllers;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function requestToken(Request $request)
    {
        $app = $this->app;

        $consumerKey = $app['pocket']['consumer_key'];
        $state = $this->getOAuthState($app);

        /** @var \GuzzleHttp\Message\ResponseInterface $response */
        $response = $app['http_client']->post(
            $requestTokenEndpoint = $this->getRequestTokenEndpoint(),
            [
                'headers'   => $this->getHeaders(),
                'body'      => [
                    'consumer_key' => $consumerKey,
                    'redirect_uri' => $this->getOauthCallbackUrl($request),
                    'state' => $state
                ]
            ]
        );

        $decodedResponseBody = $this->decodeResponseBody($response->getBody());
        $this->checkState($decodedResponseBody, $state);

        return $decodedResponseBody['code'];
    }

    /**
     * @param Request $request
     * @param $token
     * @return mixed
     */
    public function getAuthorizeApplicationUrl(Request $request, $token)
    {
        return $this->getAuthorizeRequestTokenEndpoint($token, $this->getOauthCallbackUrl($request));
    }

    /**
     * @return string
     */
    protected function getRequestTokenEndpoint()
    {
        return 'https://getpocket.com/v3/oauth/request';
    }

    /**
     * @return string
     */
    protected function getAuthorizeRequestTokenEndpoint($requestToken, $redirectUri)
    {
        return sprintf(
            'https://getpocket.com/auth/authorize?request_token=%s&redirect_uri=%s',
            $requestToken,
            urlencode($redirectUri)
        );
    }

    /**
     * @param Application $app
     * @return string
     */
    protected function getOAuthState(Application $app)
    {
        return sha1($app['authorization']['secret']);
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function getOauthCallbackUrl(Request $request)
    {
        return $request->getSchemeAndHttpHost() . '/oauth/callback';
    }

    protected function getHeaders()
    {
        return [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Accept' => 'application/json'
        ];
    }

    /**
     * @param $app
     * @return mixed
     */
    public function convertRequestTokenIntoAccessToken($app)
    {
        /** @var \GuzzleHttp\Message\ResponseInterface $response */
        $response = $app['http_client']->post(
            $this->getConvertRequestTokenIntoAccesTokenEndpoint(),
            [
                'headers' => $this->getHeaders(),
                'body' => [
                     'consumer_key' => $app['pocket']['consumer_key'],
                     'code' => $this->token
                ]
            ]
        );

        return $this->decodeResponseBody($response->getBody());
    }

    /**
     * @return string
     */
    protected function getConvertRequestTokenIntoAccesTokenEndpoint()
    {
        return 'https://getpocket.com/v3/oauth/authorize';
    }

    /**
     * @param $responseBody
     * @return mixed
     */
    protected function decodeResponseBody($responseBody)
    {
        $decodedResponseBody = json_decode($responseBody, true);
        $jsonLastError = json_last_error();
        if ($jsonLastError !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                sprintf(
                    'OAuth token response body could not be decoded (json error code: %d)',
                    $jsonLastError
                )
            );
        } else {
            return $decodedResponseBody;
        }
    }

    /**
     * @param $body
     */
    protected function checkState($body)
    {
        if ($body['state'] !== $this->getOAuthState($this->app)) {
            throw new \RuntimeException(
                sprintf(
                    'The response state should equal to state added to request body'
                )
            );
        }
    }
}