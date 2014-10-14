<?php

namespace WeavingTheWeb\OwnYourData\Console\Command;

use GuzzleHttp\Client;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Translator;

use Symfony\Component\Yaml\Parser;

use WeavingTheWeb\OwnYourData\Exception\InstallationException;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class RetrievePocketDataCommand extends Command
{
    const ITEMS_RETRIEVAL_LIMIT = 2;

    /**
     * @var \Symfony\Component\Translation\Translator
     */
    private $translator;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var \GuzzleHttp\Client
     */
    public $client;

    public $configurationFilename = 'config.yml';

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->client = new Client();

        $this->setupTranslator();
    }

    /**
     * Configure command name and description
     */
    protected function configure()
    {
        $this->setName('weaving_the_web:pocket:retrieve')
            ->setDescription("Command-line application to retrieve a user's pocket data via Pocket API");
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
        $this->output = $output;

        $configurationFile = __DIR__ . '/../../Resources/config/' . $this->configurationFilename;
        if ($this->canNotFindConfigurationFile($configurationFile)) {
            InstallationException::throwNotFoundConfigurationException($configurationFile);
        }

        $configuration = $this->parseConfiguration($configurationFile);
        if ($this->isConsumerKeyMissing($configuration)) {
            InstallationException::throwMissingConsumerKey($configurationFile);
        }

        $decodedJsonResponseBody = $this->retrieveItems($configuration);
        $itemsCount = $this->countItemsInResponse($decodedJsonResponseBody);

        $this->displayCountOfRetrievedItems($itemsCount);
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
     * @param $configurationFile
     * @return bool
     */
    protected function canNotFindConfigurationFile($configurationFile)
    {
        return !file_exists($configurationFile);
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
     * @param $configurationFile
     * @return array|bool|mixed|null
     */
    protected function parseConfiguration($configurationFile)
    {
        $parser = new Parser();

        return $parser->parse(file_get_contents($configurationFile));
    }

    /**
     * @param $configuration
     * @return string
     */
    protected function buildItemsRetrievalEndpoint($configuration)
    {
        return $configuration['pocket']['endpoint'] . '/get';
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
     * @param $configuration
     * @return mixed
     */
    protected function retrieveItems($configuration)
    {
        /** @var \GuzzleHttp\Message\ResponseInterface $response */
        $response = $this->client->post(
            $this->buildItemsRetrievalEndpoint($configuration),
            [
                'headers' => $this->getHeaders(),
                'body' => [
                    'access_token' => $configuration['pocket']['access_token'],
                    'consumer_key' => $configuration['pocket']['consumer_key'],
                    'count' => self::ITEMS_RETRIEVAL_LIMIT,
                    'detailType' => 'complete'
                ]
            ]
        );
        $decodedJsonResponseBody = $response->json();
        $this->displayResponseError($decodedJsonResponseBody);

        return $decodedJsonResponseBody;
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
}
