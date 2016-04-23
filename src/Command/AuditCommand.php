<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Command;

use SetBased\Audit\Columns;
use SetBased\Audit\ConsoleOutput;
use SetBased\Audit\MySql\DataLayer;
use SetBased\Audit\Table;
use SetBased\Audit\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
class AuditCommand extends Command
{
  //--------------------------------------------------------------------------------------------------------------------
  use ConsoleOutput;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Array of tables from audit schema.
   *
   * @var array
   */
  private $auditSchemaTables;

  /**
   * All config file as array.
   *
   * @var array
   */
  private $config;

  /**
   * Config file name.
   *
   * @var string
   */
  private $configFileName;

  /**
   * Array of tables from data schema.
   *
   * @var array
   */
  private $dataSchemaTables;

  /**
   * If true remove all column information from config file.
   *
   * @var boolean
   */
  private $pruneOption;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares the tables listed in the config file and the tables found in the audit schema
   *
   * @param string  $tableName Name of table
   * @param Columns $columns   The table columns.
   */
  public function getColumns($tableName, $columns)
  {
    $new_columns = [];
    foreach ($columns->getColumns() as $column)
    {
      $new_columns[] = ['column_name' => $column['column_name'],
                        'column_type' => $column['column_type']];
    }
    $this->config['table_columns'][$tableName] = $new_columns;

    if ($this->pruneOption)
    {
      $this->config['table_columns'] = [];
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting list of all tables from information_schema of database from config file.
   */
  public function listOfTables()
  {
    $this->dataSchemaTables = DataLayer::getTablesNames($this->config['database']['data_schema']);

    $this->auditSchemaTables = DataLayer::getTablesNames($this->config['database']['audit_schema']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Reads configuration parameters from the configuration file.
   *
   * @param string $theConfigFilename
   */
  public function readConfigFile($theConfigFilename)
  {
    $content = file_get_contents($theConfigFilename);

    $this->config = json_decode($content, true);

    if (!isset($this->config['audit_columns']))
    {
      $this->config['audit_columns'] = [];
    }

    foreach ($this->config['tables'] as $table_name => $params)
    {
      $this->config['tables'][$table_name]['audit'] = filter_var($params['audit'], FILTER_VALIDATE_BOOLEAN);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Found tables in config file
   *
   * Compares the tables listed in the config file and the tables found in the data schema
   */
  public function unknownTables()
  {
    foreach ($this->dataSchemaTables as $table)
    {
      if (isset($this->config['tables'][$table['table_name']]))
      {
        if (!isset($this->config['tables'][$table['table_name']]['audit']))
        {
          $this->output->writeln(sprintf('<info>Audit flag is not set in table %s</info>', $table['table_name']));
        }
        else
        {
          if ($this->config['tables'][$table['table_name']]['audit'])
          {
            $this->config['tables'][$table['table_name']]['alias'] = Table::getRandomAlias();
          }
        }
      }
      else
      {
        $this->output->writeln(sprintf('<info>Found new table %s</info>', $table['table_name']));
        $this->config['tables'][$table['table_name']] = ['audit' => false,
                                                         'alias' => null,
                                                         'skip'  => null];
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this->setName('audit')
         ->setDescription('Create (missing) audit table and (re)creates audit triggers')
         ->addArgument('config file', InputArgument::OPTIONAL, 'The audit configuration file', 'etc/audit.json');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->output = $output;

    // Create style for database objects.
    $style = new OutputFormatterStyle('green', null, ['bold']);
    $output->getFormatter()->setStyle('dbo', $style);

    // Create style for SQL statements.
    $style = new OutputFormatterStyle('magenta', null, ['bold']);
    $output->getFormatter()->setStyle('sql', $style);

    DataLayer::setLog($output);

    $this->configFileName = $input->getArgument('config file');

    // Read config file name, config content and then save in variable
    $this->readConfigFile($this->configFileName);

    // Create database connection with params from config file
    DataLayer::connect($this->config['database']['host_name'], $this->config['database']['user_name'],
                       $this->config['database']['password'], $this->config['database']['data_schema']);
    DataLayer::setAdditionalSQL($this->config['additional_sql']);

    $this->listOfTables();

    $this->unknownTables();

    foreach ($this->dataSchemaTables as $table)
    {
      if ($this->config['tables'][$table['table_name']]['audit'])
      {
        $tableColumns = [];
        if (isset($this->config['table_columns'][$table['table_name']]))
        {
          $tableColumns = $this->config['table_columns'][$table['table_name']];
        }
        $currentTable = new Table($this->output,
                                  $table['table_name'],
                                  $this->config['database']['data_schema'],
                                  $this->config['database']['audit_schema'],
                                  $tableColumns,
                                  $this->config['audit_columns'],
                                  $this->config['tables'][$table['table_name']]['alias'],
                                  $this->config['tables'][$table['table_name']]['skip']);
        $res          = DataLayer::searchInRowSet('table_name', $currentTable->getTableName(), $this->auditSchemaTables);
        if (!isset($res))
        {
          $currentTable->createMissingAuditTable();
        }

        $columns = $currentTable->main();
        if (empty($columns['altered_columns']))
        {
          $this->getColumns($currentTable->getTableName(), $columns['full_columns']);
        }
      }
    }

    // Drop database connection
    DataLayer::disconnect();

    $this->rewriteConfig();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Write new data to config file.
   */
  private function rewriteConfig()
  {
    Util::writeTwoPhases($this->configFileName, json_encode($this->config, JSON_PRETTY_PRINT));
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
