<?php

if (array_key_exists('REQUEST_URI', $_SERVER)) {
    $filename = __DIR__ . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);

    if (php_sapi_name() === 'cli-server' && is_file($filename)) {
        return false;
    }
}

require_once __DIR__.'/../vendor/autoload.php';

return new \WeavingTheWeb\OwnYourData\Application\Authorization();
