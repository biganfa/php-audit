<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Command;

use SetBased\Audit\MySql\AuditDataLayer;
use SetBased\Stratum\Style\StratumStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Command for dropping all triggers.
 */
class DropTriggersCommand extends MySqlBaseCommand
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this->setName('drop-triggers')
         ->setDescription('Drops all triggers')
         ->setHelp('Drops all triggers (including triggers not created by audit) from all tables (including tables '.
                   'excluded for auditing) in the data schema.')
         ->addArgument('config file', InputArgument::REQUIRED, 'The audit configuration file');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io = new StratumStyle($input, $output);

    $this->configFileName = $input->getArgument('config file');
    $this->readConfigFile();

    // Create database connection with params from config file
    $this->connect($this->config);

    $this->dropTriggers();

    // Drop database connection
    AuditDataLayer::disconnect();

    $this->rewriteConfig();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Drops all triggers.
   */
  private function dropTriggers()
  {
    $data_schema = $this->config['database']['data_schema'];
    $triggers    = AuditDataLayer::getTriggers($data_schema);
    foreach ($triggers as $trigger)
    {
      $this->io->logInfo('Dropping trigger <dbo>%s</dbo> from table <dbo>%s</dbo>',
                         $trigger['trigger_name'],
                         $trigger['table_name']);

      AuditDataLayer::dropTrigger($data_schema, $trigger['trigger_name']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
