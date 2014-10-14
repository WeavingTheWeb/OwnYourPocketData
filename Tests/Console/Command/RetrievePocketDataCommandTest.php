<?php

namespace WeavingTheWeb\OwnYourData\Tests\Console\Command;

use Prophecy\Argument;
use Prophecy\Prophet;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tests\Helper\HelperSetTest;

use Symfony\Component\Filesystem\Filesystem;

use Symfony\Component\Finder\Finder;
use WeavingTheWeb\OwnYourData\Console\Command\RetrievePocketDataCommand;
use WeavingTheWeb\OwnYourData\Exception\ConfigurationException;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class RetrievePocketDataCommandTest extends \PHPUnit_Framework_TestCase
{
    const COMMAND_NAME = 'weaving_the_web:pocket:retrieve';

    /**
     * @var Application
     */
    private $application;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var integer
     */
    private $defaultItemsRetrievalLimit;

    /**
     * @var RetrievePocketDataCommand
     */
    private $testedCommand;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * @var \Symfony\Component\Finder\Finder
     */
    private $finder;

    /**
     * @var Prophet
     */
    private $prophet;

    /**
     * @var string
     */
    private $targetDirectory;

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

        $testedCommand = $this->application->find(self::COMMAND_NAME);

        $this->defaultItemsRetrievalLimit = 2;

        $testedCommand->httpClient = $this->mockHttpClient();

        $this->testedCommand = $testedCommand;

        $this->commandTester = new CommandTester($this->testedCommand);

        $this->filesystem = new Filesystem();

        $this->finder = new Finder();

        $this->targetDirectory = __DIR__ . '/../../../Resources/documents';
    }

    public function tearDown()
    {
        $this->prophet->checkPredictions();

        $this->finder
            ->files()
            ->depth(1)
            ->in($this->targetDirectory)
            ->name('*document.json');

        foreach ($this->finder as $document) {
            $this->filesystem->remove($document);
        }

        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_should_display_an_error_message_when_executed_without_configuration()
    {
        $retrievePocketDataCommand = $this->application->find(self::COMMAND_NAME);

        $retrievePocketDataCommand->configurationFilename = 'non_existing_configuration_file.yml';
        $returnCode = $this->commandTester->execute($this->getNoInteractionInput());

        $this->assertEquals($returnCode, ConfigurationException::CONFIGURATION_NOT_FOUND,
            'It should return the "not found configuration" error code.');
    }

    /**
     * @test
     */
    public function it_should_throw_an_configuration_exception_when_executed_without_consumer_key()
    {
        $retrievePocketDataCommand = $this->application->find(self::COMMAND_NAME);

        $retrievePocketDataCommand->configurationFilename = 'config.yml.dist';
        $returnCode = $this->commandTester->execute($this->getNoInteractionInput());

        $this->assertEquals($returnCode, ConfigurationException::CONSUMER_KEY_MISSING,
            'It should return the "missing consumer key" error code.');
    }
    
    /**
     * @test
     */
    public function it_should_throw_an_configuration_exception_when_executed_with_invalid_target_directory()
    {
        $retrievePocketDataCommand = $this->application->find(self::COMMAND_NAME);

        $testConfigurationDirectory = $this->getRelativeTestConfigurationDirectory();
        $retrievePocketDataCommand->configurationFilename = $testConfigurationDirectory .
            '/config_with_invalid_target_directory.yml';
        $returnCode = $this->commandTester->execute($this->getNoInteractionInput());

        $this->assertEquals($returnCode, ConfigurationException::TARGET_DIRECTORY_INVALID,
            'It should return the "invalid target directory" error code.');
    }

    /**
     * @return string
     */
    public function getRelativeTestConfigurationDirectory()
    {
        return '/../../Tests/Resources/config';
    }

    /**
     * @test
     */
    public function it_should_ask_the_user_for_the_items_retrieval_limit()
    {
        $this->setUpValidConfiguration();

        $dialog = $this->testedCommand->getHelper('question');
        $dialog->setInputStream($this->getInputStream(sprintf("%d\n", $this->defaultItemsRetrievalLimit)));

        $this->commandTester->execute(['command' => self::COMMAND_NAME]);
        $this->assertCountItemsDisplayed($this->defaultItemsRetrievalLimit);
    }

    public function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input);
        rewind($stream);

        return $stream;
    }

    public function testExecute()
    {
        $this->setUpValidConfiguration();

        $this->commandTester->execute($this->getNoInteractionInput());

        $display = $this->commandTester->getDisplay();
        $unexpectedExceptionMessage = 'Exception';
        $this->assertNotContains($unexpectedExceptionMessage, $display,
            sprintf('Executing the command should not display an exception message.', $unexpectedExceptionMessage));

        $this->assertCountItemsDisplayed($this->defaultItemsRetrievalLimit);
    }

    /**
     * @return array
     */
    protected function getNoInteractionInput()
    {
        return ['command' => self::COMMAND_NAME, '--no-interaction' => true];
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

    protected function setUpValidConfiguration()
    {
        $testConfigurationDirectory = $this->getRelativeTestConfigurationDirectory();

        $this->testedCommand->configurationFilename = $testConfigurationDirectory . '/config.yml';
    }

    /**
     * @param $expectedItemsCount
     */
    protected function assertCountItemsDisplayed($expectedItemsCount)
    {
        $expectedMessage = $expectedItemsCount . ' items have been retrieved';
        $this->assertContains(
            $expectedMessage,
            $this->commandTester->getDisplay(),
            'Command execution should output the number of items retrieved'
        );
    }
}
