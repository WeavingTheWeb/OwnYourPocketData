<?php

namespace WeavingTheWeb\OwnYourData\Exception;

/**
 * @author  Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class InstallationException extends \RuntimeException
{
    const CONFIGURATION_NOT_FOUND = 10;

    const CONSUMER_KEY_MISSING = 20;

    public static function throwNotFoundConfigurationException($configurationFile)
    {
        $errorCode = self::CONFIGURATION_NOT_FOUND;
        $message = sprintf('the configuration file cannot be found where it is assumed to be ("%s")', $configurationFile);

        self::throwInstallationException($message, $errorCode);
    }

    public static function throwMissingConsumerKey($configurationFile)
    {
        $errorCode = self::CONSUMER_KEY_MISSING;
        $message = sprintf('the consumer key of your Pocket application is missing in "%s"', $configurationFile);

        self::throwInstallationException($message, $errorCode);
    }

    /**
     * @return string
     */
    protected static function getDocumentationLink()
    {
        return 'https://github.com/WeavingTheWeb/OwnYourPocketData/blob/master/README.md';
    }

    /**
     * @return string
     */
    protected static function getSubmitGitHubIssueLink()
    {
        return 'https://github.com/WeavingTheWeb/OwnYourPocketData/issues/new';
    }

    /**
     * @param $message
     * @return string
     */
    protected static function decorateExceptionMessage($message, $errorCode)
    {
        return sprintf(
            'Sorry, %s' . "\n" .
            'Please read the documentation (%s)' . "\n" .
            'or submit an issue on GitHub (%s) with error code (%d)',
            $message,
            self::getDocumentationLink(),
            self::getSubmitGitHubIssueLink(),
            $errorCode
        );
    }

    /**
     * @param $message
     * @param $errorCode
     */
    protected static function throwInstallationException($message, $errorCode)
    {
        throw new self(self::decorateExceptionMessage($message, $errorCode), $errorCode);
    }
}