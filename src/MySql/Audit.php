<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql;

use SetBased\Audit\MySql\Metadata\TableColumnsMetadata;
use SetBased\Audit\MySql\Metadata\TableMetadata;
use SetBased\Stratum\MySql\StaticDataLayer;
use SetBased\Stratum\Style\StratumStyle;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for executing auditing actions for tables.
 */
class Audit
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata (additional) audit columns (as stored in the config file).
   *
   * @var TableColumnsMetadata
   */
  private $auditColumnsMetadata;

  /**
   * The names of all tables in audit schema.
   *
   * @var array
   */
  private $auditSchemaTables;

  /**
   * The content of the configuration file.
   *
   * @var array
   */
  private $config;

  /**
   * The names of all tables in data schema.
   *
   * @var array
   */
  private $dataSchemaTables;

  /**
   * The Output decorator.
   *
   * @var StratumStyle
   */
  private $io;

  /**
   * If true remove all column information from config file.
   *
   * @var boolean
   */
  private $pruneOption;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[]      $config The content of the configuration file.
   * @param StratumStyle $io     The Output decorator.
   */
  public function __construct(&$config, $io)
  {
    $this->config = $config;
    $this->io     = $io;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting list of all tables from information_schema of database from config file.
   */
  public function listOfTables()
  {
    $this->dataSchemaTables = AuditDataLayer::getTablesNames($this->config['database']['data_schema']);

    $this->auditSchemaTables = AuditDataLayer::getTablesNames($this->config['database']['audit_schema']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The main method: executes the auditing actions for tables.
   */
  public function main()
  {
    if ($this->pruneOption)
    {
      $this->config['table_columns'] = [];
    }

    $this->resolveCanonicalAuditColumns();

    $this->listOfTables();

    $this->unknownTables();

    $this->knownTables();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Sets the columns metadata of a table in the configuration file.
   *
   * @param string               $tableName The name of table.
   * @param TableColumnsMetadata $columns   The metadata of the table columns.
   */
  public function setConfigTableColumns($tableName, $columns)
  {
    $newColumns = [];
    foreach ($columns->getColumns() as $column)
    {
      $newColumns[] = $column->getProperties();
    }
    $this->config['table_columns'][$tableName] = $newColumns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares the tables listed in the config file and the tables found in the data schema.
   */
  public function unknownTables()
  {
    foreach ($this->dataSchemaTables as $table)
    {
      if (isset($this->config['tables'][$table['table_name']]))
      {
        if (!isset($this->config['tables'][$table['table_name']]['audit']))
        {
          $this->io->writeln(sprintf('<info>audit is not set for table %s</info>', $table['table_name']));
        }
        else
        {
          if ($this->config['tables'][$table['table_name']]['audit'])
          {
            if (!isset($this->config['tables'][$table['table_name']]['alias']))
            {
              $this->config['tables'][$table['table_name']]['alias'] = AuditTable::getRandomAlias();
            }
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
   * Resolves the canonical column types of the audit table columns.
   */
  protected function resolveCanonicalAuditColumns()
  {
    // Return immediately of there are no audit columns.
    if (empty($this->config['audit_columns'])) return;

    $schema    = $this->config['database']['audit_schema'];
    $tableName = '_TMP_'.uniqid();
    AuditDataLayer::createTemporaryTable($schema, $tableName, $this->config['audit_columns']);
    $columns = AuditDataLayer::getTableColumns($schema, $tableName);
    AuditDataLayer::dropTemporaryTable($schema, $tableName);

    foreach ($this->config['audit_columns'] as $audit_column)
    {
      $key = StaticDataLayer::searchInRowSet('column_name', $audit_column['column_name'], $columns);
      if (isset($audit_column['value_type']))
      {
        $columns[$key]['value_type'] = $audit_column['value_type'];
      }
      if (isset($audit_column['expression']))
      {
        $columns[$key]['expression'] = $audit_column['expression'];
      }
    }

    $this->auditColumnsMetadata = new TableColumnsMetadata($columns, 'AuditColumnMetadata');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Processed known tables.
   */
  private function knownTables()
  {
    foreach ($this->dataSchemaTables as $table)
    {
      if ($this->config['tables'][$table['table_name']]['audit'])
      {
        $tableColumns = [];
        if (isset($this->config['table_columns'][$table['table_name']]))
        {
          $tableColumns = $this->config['table_columns'][$table['table_name']];
        }
        $configTable = new TableMetadata($table['table_name'],
                                         $this->config['database']['data_schema'],
                                         $tableColumns);

        $currentTable = new AuditTable($this->io,
                                       $configTable,
                                       $this->config['database']['audit_schema'],
                                       $this->auditColumnsMetadata,
                                       $this->config['tables'][$table['table_name']]['alias'],
                                       $this->config['tables'][$table['table_name']]['skip']);
        $res          = StaticDataLayer::searchInRowSet('table_name',
                                                        $currentTable->getTableName(),
                                                        $this->auditSchemaTables);
        if (!isset($res))
        {
          $currentTable->createMissingAuditTable();
        }

        $columns        = $currentTable->main($this->config['additional_sql']);
        $alteredColumns = $columns['altered_columns']->getColumns();
        if (empty($alteredColumns))
        {
          $this->setConfigTableColumns($currentTable->getTableName(), $columns['full_columns']);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
