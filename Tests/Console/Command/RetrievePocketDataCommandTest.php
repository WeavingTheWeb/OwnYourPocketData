<?php

namespace WeavingTheWeb\OwnYourData\Tests\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use WeavingTheWeb\OwnYourData\Console\Command\RetrievePocketDataCommand;
use WeavingTheWeb\OwnYourData\Exception\InstallationException;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class RetrievePocketDataCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $ownYourDataApplication = new Application();
        $retrievePocketDataCommand = new RetrievePocketDataCommand();

        $originalConfigurationFilename = $retrievePocketDataCommand->configurationFilename;
        $ownYourDataApplication->add($retrievePocketDataCommand);

        $commandName = 'weaving_the_web:pocket:retrieve';
        $retrievePocketDataCommand = $ownYourDataApplication->find($commandName);
        $commandTester = new CommandTester($retrievePocketDataCommand);

        try {
            $retrievePocketDataCommand->configurationFilename = 'non_existing_configuration_file.yml';
            $commandTester->execute(['command' => $commandName]);
        } catch (\Exception $exception) {
            $this->assertInstanceOf('WeavingTheWeb\OwnYourData\Exception\InstallationException', $exception);
            $this->assertEquals(InstallationException::CONFIGURATION_NOT_FOUND, $exception->getCode());
        }

        try {
            $retrievePocketDataCommand->configurationFilename = 'config.yml.dist';
            $commandTester->execute(['command' => $commandName]);
        } catch (\Exception $exception) {
            $this->assertInstanceOf('WeavingTheWeb\OwnYourData\Exception\InstallationException', $exception);
            $this->assertEquals(InstallationException::CONSUMER_KEY_MISSING, $exception->getCode());
        }

        $retrievePocketDataCommand->configurationFilename = '../../Tests/Resources/config/config.yml';
        $commandTester->execute(['command' => $commandName]);

        $display = $commandTester->getDisplay();
        $unexpectedExceptionMessage = 'Exception';
        $this->assertNotContains($unexpectedExceptionMessage, $display,
            sprintf('Executing the command should not display an exception message.', $unexpectedExceptionMessage));
    }
}
