<?php

namespace WeavingTheWeb\OwnYourData\Console\Command;

use GuzzleHttp\Client;

use GuzzleHttp\Exception\ServerException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Question\Question;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Translator;

use Symfony\Component\Yaml\Parser;

use WeavingTheWeb\OwnYourData\Exception\ConfigurationException;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class RetrievePocketDataCommand extends Command
{
    const DEFAULT_ITEMS_RETRIEVAL_LIMIT = 10000;

    /**
     * @var \Symfony\Component\Translation\Translator
     */
    private $translator;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $configurationFilePath;

    /**
     * Holds configuration for
     *  - third-party Pocket application OAuth access token, consumer key
     *  - Target directory (where retrieved items are to be stored)
     *  - Application secret (used to generate OAuth state)
     *
     * @var array
     */
    protected $configuration;

    /**
     * @var string
     */
    public $configurationFilename = 'config.yml';

    /**
     * @var \GuzzleHttp\Client
     */
    public $httpClient;

    /**
     * Directory where items will be saved after retrieval
     *
     * @var string
     */
    public $targetDirectory;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->httpClient = new Client();

        $this->setupTranslator();
    }

    /**
     * Configure command name and description
     */
    protected function configure()
    {
        $this->setName('weaving_the_web:pocket:retrieve')
            ->setDescription("Command-line application to retrieve a user's pocket data via Pocket API")
            ->setAliases(['wtw:pkt:get']);
    }

    /**
     * Retrieve pocket data
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $this->configurationFilePath = __DIR__ . '/../../Resources/config/' . $this->configurationFilename;

            $this->validateConfigurationReadability();

            $this->configuration = $this->parseConfiguration($this->configurationFilePath);

            $this->targetDirectory = $this->configuration['items_retrieval']['target_directory'];
            $this->validateTargetDirectory();

            $this->validateConsumerKey();
        } catch (ConfigurationException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return $exception->getCode();
        }

        $itemsRetrievalLimit = $this->getItemsRetrievalLimit();

        $decodedJsonResponseBody = $this->retrieveItems($itemsRetrievalLimit);

        $itemsCount = $this->countItemsInResponse($decodedJsonResponseBody);

        $this->displayCountOfRetrievedItems($itemsCount);
    }

    /**
     * @param InputInterface $input
     * @return bool
     */
    protected function isInteractiveMode(InputInterface $input)
    {
        return !$input->hasOption('no-interaction') || !$input->getOption('no-interaction');
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Accept' => 'application/json'
        ];
    }

    /**
     * @param $configuration
     * @return bool
     */
    protected function isConsumerKeyMissing($configuration)
    {
        return !array_key_exists('pocket', $configuration) ||
        !array_key_exists('consumer_key', $configuration['pocket']) ||
        is_null($configuration['pocket']['consumer_key']);
    }

    /**
     * @param $filePath
     * @return array|bool|mixed|null
     */
    protected function parseConfiguration($filePath)
    {
        $parser = new Parser();

        return $parser->parse(file_get_contents($filePath));
    }

    /**
     * @return string
     */
    protected function buildItemsRetrievalEndpoint()
    {
        return $this->configuration['pocket']['endpoint'] . '/get';
    }

    /**
     * @param $body
     */
    protected function displayResponseError($body)
    {
        if (array_key_exists('error', $body) && !is_null($body['error'])) {
            $this->output->writeln($body['error']);
        }
    }

    /**
     * @param $body
     * @return int
     */
    protected function countItemsInResponse($body)
    {
        if (array_key_exists('list', $body)) {
            $itemsCount = count($body['list']);
        } else {
            $itemsCount = 0;
        }

        return $itemsCount;
    }

    /**
     * @param $itemsCount
     */
    protected function displayCountOfRetrievedItems($itemsCount)
    {
        $itemsRetrievedMessage = $this->translator->transChoice(
            'items_retrieved',
            $itemsCount,
            ['{{ count }}' => $itemsCount],
            'command',
            'en_GB'
        );
        $this->output->writeln($itemsRetrievedMessage);
    }

    /**
     * Retrieves items by sending an HTTP request to configured endpoint.
     *
     * @param $limit
     * @return mixed
     */
    protected function retrieveItems($limit)
    {
        try {
            /** @var \GuzzleHttp\Message\ResponseInterface $response */
            $response = $this->httpClient->post(
                $this->buildItemsRetrievalEndpoint(),
                [
                    'headers' => $this->getHeaders(),
                    'body' => [
                        'access_token' => $this->configuration['pocket']['access_token'],
                        'consumer_key' => $this->configuration['pocket']['consumer_key'],
                        'count' => $limit,
                        'detailType' => 'complete',
                        'since' => 1283810400,
                        'offset' => 0,
                    ]
                ]
            );
            $decodedJsonResponseBody = $response->json();

            $targetDocument = $this->targetDirectory . '/' . date('Ymd_H:i:s') . '_document.json';
            file_put_contents($targetDocument, json_encode($decodedJsonResponseBody, JSON_PRETTY_PRINT));

            $this->displayResponseError($decodedJsonResponseBody);

            return $decodedJsonResponseBody;
        } catch (ServerException $serverException) {
            $this->output->writeln($serverException->getMessage());

            return [];
        }
    }

    /**
     * @return Translator
     */
    protected function setupTranslator()
    {
        $this->translator = new Translator('en_GB', new MessageSelector());
        $this->translator->addLoader('yaml', new YamlFileLoader());
        $this->translator->addResource('yaml', __DIR__ . '/../../Resources/translations/command.en.yml', 'en_GB', 'command');
    }

    /**
     * @return array|bool|mixed|null
     */
    protected function validateConfigurationReadability()
    {
        if (!$this->isConfigurationFileReadable($this->configurationFilePath)) {
            ConfigurationException::throwNotFoundConfigurationException($this->configurationFilePath);
        }
    }

    /**
     * Returns true when the configuration file exists and it is readable, false otherwise.
     *
     * @param $file
     * @return bool
     */
    protected function isConfigurationFileReadable($file)
    {
        return file_exists($file) || is_readable($file);
    }

    protected function validateConsumerKey()
    {
        if ($this->isConsumerKeyMissing($this->configuration)) {
            ConfigurationException::throwMissingConsumerKey($this->configurationFilePath);
        }
    }


    protected function validateTargetDirectory()
    {
        if (!is_dir($this->targetDirectory)) {
            ConfigurationException::throwInvalidTargetDirectory($this->targetDirectory, $this->configurationFilePath);
            throw new ConfigurationException('The configured target directory is not a valid directory.');
        }
    }

    /**
     * Asks user about the number of items to be retrieved or pick the default value.
     *
     * @return int|null|string
     */
    protected function getItemsRetrievalLimit()
    {
        if ($this->isInteractiveMode($this->input)) {
            $askForItemRetrievalLimitMessage = $this->translator->trans(
                'dialog.ask.items_retrieval_limit', [], 'command', 'en_GB') . "\n";
            $question = new Question($askForItemRetrievalLimitMessage);
            $question->setValidator(function ($answer) {
                if (!is_numeric($answer) or $answer < 0) {
                    throw new \RuntimeException('The number of items to be retrieved should be a positive integer');
                }

                return $answer;
            });
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $dialog */
            $dialog = $this->getHelper('question');
            $itemsRetrievalLimit = $dialog->ask($this->input, $this->output, $question);
        } else {
            $itemsRetrievalLimit = self::DEFAULT_ITEMS_RETRIEVAL_LIMIT;
        }

        return $itemsRetrievalLimit;
    }
}
