<?php

namespace WeavingTheWeb\OwnYourData\Application;

use Igorw\Silex\ConfigServiceProvider;

use Silex\Application;

use Silex\Provider\SessionServiceProvider;

use WeavingTheWeb\OwnYourData\Provider\HttpClientProvider;
use WeavingTheWeb\OwnYourData\Provider\OauthControllerProvider;

class Authorization extends Application
{
    /**
     * @param array $values
     */
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $this->configure();
    }

    public function configure()
    {
        $configurationFile = __DIR__ . '/../Resources/config/config.yml';
        $this->register(new ConfigServiceProvider($configurationFile));

        $this->register(new SessionServiceProvider());

        $this->register(new HttpClientProvider());

        $this->mount('/oauth', new OauthControllerProvider());
    }
}