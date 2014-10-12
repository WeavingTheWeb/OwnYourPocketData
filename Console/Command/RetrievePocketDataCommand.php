<?php

namespace WeavingTheWeb\OwnYourData\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Yaml\Parser;

use WeavingTheWeb\OwnYourData\Exception\InstallationException;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class RetrievePocketDataCommand extends Command
{
    public $configurationFilename = 'config.yml';

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
        $configurationFile = __DIR__ . '/../../Resources/config/' . $this->configurationFilename;
        if ($this->canNotFindConfigurationFile($configurationFile)) {
            InstallationException::throwNotFoundConfigurationException($configurationFile);
        }

        $configuration = $this->parseConfiguration($configurationFile);
        if ($this->isConsumerKeyMissing($configuration)) {
            InstallationException::throwMissingConsumerKey($configurationFile);
        }
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
}