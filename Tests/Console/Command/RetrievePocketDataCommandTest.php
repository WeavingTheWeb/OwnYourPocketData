<?php

namespace WeavingTheWeb\OwnYourData\Tests\Console\Command;

use Prophecy\Argument;
use Prophecy\Prophet;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use WeavingTheWeb\OwnYourData\Console\Command\RetrievePocketDataCommand;
use WeavingTheWeb\OwnYourData\Exception\InstallationException;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class RetrievePocketDataCommandTest extends \PHPUnit_Framework_TestCase
{
    const COMMAND_NAME = 'weaving_the_web:pocket:retrieve';

    /**
     * @var RetrievePocketDataCommand
     */
    private $testedCommand;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var Application
     */
    private $application;

    /**
     * @var Prophet
     */
    private $prophet;

    public function setUp()
    {
        parent::setUp();

        $this->prophet = new Prophet();

        $this->setUpTestedCommand();
    }

    protected function setUpTestedCommand()
    {
        $this->application = new Application();

        $retrievePocketDataCommand = new RetrievePocketDataCommand();

        $this->application->add($retrievePocketDataCommand);

        $this->testedCommand = $this->application->find(self::COMMAND_NAME);
        $this->testedCommand->client = $this->mockHttpClient();

        $this->commandTester = new CommandTester($this->testedCommand);
    }

    public function tearDown()
    {
        $this->prophet->checkPredictions();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_should_throw_an_installation_exception_when_executed_without_configuration()
    {
        $this->assertThrowNotFoundConfigurationException(self::COMMAND_NAME);
    }

    /**
     * @test
     */
    public function it_should_throw_an_installation_exception_when_executed_without_consumer_key()
    {
        $this->assertThrowMissingConsumerKey(self::COMMAND_NAME);
    }

    public function testExecute()
    {
        $this->testedCommand->configurationFilename = '../../Tests/Resources/config/config.yml';
        $this->commandTester->execute(['command' => self::COMMAND_NAME]);

        $display = $this->commandTester->getDisplay();
        $unexpectedExceptionMessage = 'Exception';
        $this->assertNotContains($unexpectedExceptionMessage, $display,
            sprintf('Executing the command should not display an exception message.', $unexpectedExceptionMessage));

        $expectedMessage = '2 items have been retrieved';
        $this->assertContains($expectedMessage, $display,
            'Command execution should output the number of items retrieved');
    }

    /**
     * @param $commandName
     * @return \Exception
     */
    protected function assertThrowNotFoundConfigurationException($commandName)
    {
        $retrievePocketDataCommand = $this->application->find($commandName);

        try {
            $retrievePocketDataCommand->configurationFilename = 'non_existing_configuration_file.yml';
            $this->commandTester->execute(['command' => $commandName]);
        } catch (\Exception $exception) {
            $this->assertInstanceOf('WeavingTheWeb\OwnYourData\Exception\InstallationException', $exception);
            $this->assertEquals(InstallationException::CONFIGURATION_NOT_FOUND, $exception->getCode());

            return $exception;
        }
    }

    /**
     * @param $commandName
     */
    protected function assertThrowMissingConsumerKey($commandName)
    {
        $retrievePocketDataCommand = $this->application->find($commandName);

        try {
            $retrievePocketDataCommand->configurationFilename = 'config.yml.dist';
            $this->commandTester->execute(['command' => $commandName]);
        } catch (\Exception $exception) {
            $this->assertInstanceOf('WeavingTheWeb\OwnYourData\Exception\InstallationException', $exception);
            $this->assertEquals(InstallationException::CONSUMER_KEY_MISSING, $exception->getCode());
        }
    }

    protected function mockHttpClient()
    {
        $responseProphecy = $this->prophet->prophesize('\GuzzleHttp\Message\Response');

        $getResponseBody = file_get_contents(__DIR__ . '/../../Resources/fixtures/get_response.json');
        $responseProphecy->json(Argument::cetera())->willReturn(json_decode($getResponseBody, true));

        $httpClientProphecy = $this->prophet->prophesize('\GuzzleHttp\Client');
        $httpClientProphecy->post(Argument::cetera())->willReturn($responseProphecy->reveal());

        return $httpClientProphecy->reveal();
    }
}
