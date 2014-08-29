<?php

use Behat\Behat\Context\BehatContext;
use Behat\Behat\Exception\PendingException;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Behat\Event\FeatureEvent;
use Behat\Behat\Context\Step\Given;
use Behat\Behat\Context\Step\Then;
use Behat\Behat\Context\Step\When;
use Drupal\DrupalExtension\Context\DrupalContext;
use Drupal\DrupalExtension\Context\DrupalSubContextInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @file
 * Provide Behat step-definitions for Migrations.
 *
 */
class MigrateSubContext extends BehatContext implements DrupalSubContextInterface
{

    /**
     * Initializes context.
     */
    public function __construct(array $parameters = array())
    {
    }

    public static function getAlias()
    {
        return 'migrate';
    }

    /**
     * @Given /^the "([^"]*)" migration is complete$/
     */
    public function assertMigrateCompletion($machine_name)
    {
        $migrate = Migration::getInstance($machine_name);
        $completion = $migrate->isComplete();

        if ($completion) {
            return TRUE;
        }

        else {
            $message = sprintf('Migration "%s" did not complete.', $machine_name);
            throw new \Exception($message);
        }

    }
}
