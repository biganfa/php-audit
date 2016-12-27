<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Command;

use SetBased\Audit\MySql\AuditAlter;
use SetBased\Stratum\Style\StratumStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Command for comparing data tables with audit tables.
 */
class AlterTableCommand extends AuditCommand
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check full full and return array without new or obsolete columns if full not set.
   *
   * @param array[] $columns The metadata of the columns of a table.
   *
   * @var StratumStyle
   */
  protected $io;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this->setName('alter-table-sql')
         ->setDescription('Create alter table SQL commands for audit tables columns that are different from the config columns or from columns data tables')
         ->addArgument('config file', InputArgument::OPTIONAL, 'The audit configuration file', 'etc/audit.json')
         ->addArgument('result sql file', InputArgument::OPTIONAL, 'The result file for SQL statement', 'etc/alter-table-sql-result.sql');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io = new StratumStyle($input, $output);

    $resultSqlFile = $input->getArgument('result sql file');

    $this->configFileName = $input->getArgument('config file');
    $this->readConfigFile();

    $this->connect($this->config);

    $alter   = new AuditAlter($this->config, $this->configMetadata);
    $content = $alter->main();

    $this->writeTwoPhases($resultSqlFile, $content);
  }

  //--------------------------------------------------------------------------------------------------------------------

}

//----------------------------------------------------------------------------------------------------------------------
