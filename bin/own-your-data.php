#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

use WeavingTheWeb\OwnYourData\Console\Command\RetrievePocketDataCommand;

$application = new Application();

$application->add(new RetrievePocketDataCommand);
$application->run();