<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Command;

use SetBased\Audit\MySql\DataLayer;
use SetBased\Audit\MySql\Table\ColumnType;
use SetBased\Audit\MySql\Table\Table;
use SetBased\Stratum\MySql\StaticDataLayer;
use SetBased\Stratum\Style\StratumStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Command for creating audit tables and audit triggers.
 */
class AuditCommand extends MySqlCommand
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * All tables in the in the audit schema.
   *
   * @var array
   */
  protected $auditSchemaTables;

  /**
   * Array of tables from data schema.
   *
   * @var array
   */
  protected $dataSchemaTables;

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
   * @param string                              $tableName Name of table
   * @param \SetBased\Audit\MySql\Table\Columns $columns   The table columns.
   */
  public function getColumns($tableName, $columns)
  {
    $newColumns = [];
    /** @var ColumnType $column */
    foreach ($columns->getColumns() as $column)
    {
      $newColumns[] = $column->getType();
    }
    $this->config['table_columns'][$tableName] = $newColumns;

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
          $this->io->writeln(sprintf('<info>AuditApplication flag is not set in table %s</info>', $table['table_name']));
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
        $this->io->writeln(sprintf('<info>Found new table %s</info>', $table['table_name']));
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
    $this->io = new StratumStyle($input, $output);

    $this->configFileName = $input->getArgument('config file');
    $this->readConfigFile();

    // Create database connection with params from config file
    $this->connect($this->config);

    $this->auditColumnTypes();

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
        $currentTable = new Table($this->io,
                                  $table['table_name'],
                                  $this->config['database']['data_schema'],
                                  $this->config['database']['audit_schema'],
                                  $tableColumns,
                                  $this->config['audit_columns'],
                                  $this->config['tables'][$table['table_name']]['alias'],
                                  $this->config['tables'][$table['table_name']]['skip']);
        $res          = StaticDataLayer::searchInRowSet('table_name', $currentTable->getTableName(), $this->auditSchemaTables);
        if (!isset($res))
        {
          $currentTable->createMissingAuditTable();
        }

        $columns        = $currentTable->main($this->config['additional_sql']);
        $alteredColumns = $columns['altered_columns']->getColumns();
        if (empty($alteredColumns))
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
   * Get canonical column types for audit columns.
   */
  protected function auditColumnTypes()
  {
    $schema    = $this->config['database']['audit_schema'];
    $tableName = 'TMP_'.uniqid();
    DataLayer::createTemporaryTable($schema, $tableName, $this->config['audit_columns']);
    $auditColumns = DataLayer::showColumns($schema, $tableName);
    foreach ($auditColumns as $column)
    {
      $key = StaticDataLayer::searchInRowSet('column_name', $column['Field'], $this->config['audit_columns']);
      if (isset($key))
      {
        $this->config['audit_columns'][$key]['column_type'] = $column['Type'];
        if ($column['Null']==='NO')
        {
          $this->config['audit_columns'][$key]['column_type'] = sprintf('%s not null', $this->config['audit_columns'][$key]['column_type']);
        }
      }
    }
    DataLayer::dropTemporaryTable($schema, $tableName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Write new data to config file.
   */
  private function rewriteConfig()
  {
    $this->writeTwoPhases($this->configFileName, json_encode($this->config, JSON_PRETTY_PRINT));
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
