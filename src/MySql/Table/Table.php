<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Table;

use SetBased\Audit\MySql\DataLayer;
use SetBased\Stratum\Style\StratumStyle;

//--------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of tables.
 */
class Table
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The unique alias for this data table.
   *
   * @var string
   */
  private $alias;

  /**
   * The metadata (additional) audit columns (as stored in the config file).
   *
   * @var Columns
   */
  private $auditColumns;

  /**
   * The name of the schema with the audit tables.
   *
   * @var string
   */
  private $auditSchemaName;

  /**
   * The name of the schema with the data tables.
   *
   * @var string
   */
  private $dataSchemaName;

  /**
   * The metadata of the columns of the data table as stored in the config file.
   *
   * @var Columns
   */
  private $dataTableColumnsConfig;

  /**
   * The metadata of the columns of the data table retrieved from information_schema.
   *
   * @var Columns
   */
  private $dataTableColumnsDatabase;

  /**
   * The output decorator
   *
   * @var StratumStyle
   */
  private $io;

  /**
   * The skip variable for triggers.
   *
   * @var string
   */
  private $skipVariable;

  /**
   * The name of this data table.
   *
   * @var string
   */
  private $tableName;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param StratumStyle $io                    The output for log messages.
   * @param string       $tableName             The table name.
   * @param string       $dataSchema            The name of the schema with data tables.
   * @param string       $auditSchema           The name of the schema with audit tables.
   * @param array[]      $configColumnsMetadata The columns of the data table as stored in the config file.
   * @param array[]      $auditColumnsMetadata  The columns of the audit table as stored in the config file.
   * @param string       $alias                 An unique alias for this table.
   * @param string       $skipVariable          The skip variable
   */
  public function __construct($io,
                              $tableName,
                              $dataSchema,
                              $auditSchema,
                              $configColumnsMetadata,
                              $auditColumnsMetadata,
                              $alias,
                              $skipVariable)
  {
    $this->io                       = $io;
    $this->tableName                = $tableName;
    $this->dataTableColumnsConfig   = new Columns($configColumnsMetadata);
    $this->dataSchemaName           = $dataSchema;
    $this->auditSchemaName          = $auditSchema;
    $this->dataTableColumnsDatabase = new Columns($this->getColumnsFromInformationSchema());
    $this->auditColumns             = new Columns($auditColumnsMetadata);
    $this->alias                    = $alias;
    $this->skipVariable             = $skipVariable;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a random alias for this table.
   *
   * @return string
   */
  public static function getRandomAlias()
  {
    return uniqid();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates missing audit table for this table.
   */
  public function createMissingAuditTable()
  {
    $this->io->logInfo('Creating audit table <dbo>%s.%s<dbo>', $this->auditSchemaName, $this->tableName);

    $columns = Columns::combine($this->auditColumns, $this->dataTableColumnsDatabase);
    DataLayer::createAuditTable($this->dataSchemaName, $this->auditSchemaName, $this->tableName, $columns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates audit triggers on this table.
   *
   * @param string[] $additionalSql Additional SQL statements to be include in triggers.
   */
  public function createTriggers($additionalSql)
  {
    // Lock the table to prevent insert, updates, or deletes between dropping and creating triggers.
    $this->lockTable($this->tableName);

    // Drop all triggers, if any.
    $this->dropTriggers();

    // Create or recreate the audit triggers.
    $this->createTableTrigger('INSERT', $this->skipVariable, $additionalSql);
    $this->createTableTrigger('UPDATE', $this->skipVariable, $additionalSql);
    $this->createTableTrigger('DELETE', $this->skipVariable, $additionalSql);

    // Insert, updates, and deletes are no audited again. So, release lock on the table.
    $this->unlockTables();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the name of this table.
   *
   * @return string
   */
  public function getTableName()
  {
    return $this->tableName;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main function for work with table.
   *
   * @param string[] $additionalSql Additional SQL statements to be include in triggers.
   *
   * @return \array[] Columns for config file
   */
  public function main($additionalSql)
  {
    $comparedColumns = null;
    if (isset($this->dataTableColumnsConfig))
    {
      $comparedColumns = $this->getTableColumnInfo();
    }

    $newColumns      = $comparedColumns['new_columns']->getColumns();
    $obsoleteColumns = $comparedColumns['obsolete_columns']->getColumns();
    if (empty($newColumns) && empty($obsoleteColumns))
    {
      $alteredColumns = $comparedColumns['altered_columns']->getColumns();
      if (empty($alteredColumns))
      {
        $this->createTriggers($additionalSql);
      }
    }

    return $comparedColumns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Adds new columns to audit table.
   *
   * @param Columns $columns Columns array
   */
  private function addNewColumns($columns)
  {
    DataLayer::addNewColumns($this->auditSchemaName, $this->tableName, $columns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a triggers for this table.
   *
   * @param string      $action      The trigger action (INSERT, DELETE, or UPDATE).
   * @param string|null $skipVariable
   * @param string[]    $additionSql The additional SQL statements to be included in triggers.
   */
  private function createTableTrigger($action, $skipVariable, $additionSql)
  {
    $triggerName = $this->getTriggerName($action);

    $this->io->logVerbose('Creating trigger <dbo>%s.%s</dbo> on table <dbo>%s.%s</dbo>',
                          $this->dataSchemaName,
                          $triggerName,
                          $this->dataSchemaName,
                          $this->tableName);

    DataLayer::createAuditTrigger($this->dataSchemaName,
                                  $this->auditSchemaName,
                                  $this->tableName,
                                  $triggerName,
                                  $action,
                                  $this->auditColumns,
                                  $this->dataTableColumnsDatabase,
                                  $skipVariable,
                                  $additionSql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Drops all triggers from this table.
   */
  private function dropTriggers()
  {
    $triggers = DataLayer::getTableTriggers($this->dataSchemaName, $this->tableName);
    foreach ($triggers as $trigger)
    {
      $this->io->logVerbose('Dropping trigger <dbo>%s</dbo> on <dbo>%s.%s</dbo>',
                            $trigger['trigger_name'],
                            $this->dataSchemaName,
                            $this->tableName);

      DataLayer::dropTrigger($this->dataSchemaName, $trigger['trigger_name']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares columns types from table in data_schema with columns in config file.
   *
   * @return Columns
   */
  private function getAlteredColumns()
  {
    $alteredColumnsTypes = Columns::differentColumnTypes($this->dataTableColumnsDatabase,
                                                         $this->dataTableColumnsConfig);

    return $alteredColumnsTypes;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects and returns the metadata of the columns of this table from information_schema.
   *
   * @return array[]
   */
  private function getColumnsFromInformationSchema()
  {
    $result = DataLayer::getTableColumns($this->dataSchemaName, $this->tableName);

    return $result;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compare columns from table in data_schema with columns in config file.
   *
   * @return \array[]
   */
  private function getTableColumnInfo()
  {
    $columnActual  = new Columns(DataLayer::getTableColumns($this->auditSchemaName, $this->tableName));
    $columnsConfig = Columns::combine($this->auditColumns, $this->dataTableColumnsConfig);
    $columnsTarget = Columns::combine($this->auditColumns, $this->dataTableColumnsDatabase);

    $newColumns      = Columns::notInOtherSet($columnsTarget, $columnActual);
    $obsoleteColumns = Columns::notInOtherSet($columnsConfig, $columnsTarget);
    $alteredColumns  = $this->getAlteredColumns();

    $this->loggingColumnInfo($newColumns, $obsoleteColumns, $alteredColumns);
    $checkNewColumns = $newColumns->getColumns();
    if (!empty($checkNewColumns))
    {
      $this->addNewColumns($newColumns);
    }

    return ['full_columns'     => $this->getTableColumnsFromConfig($newColumns, $obsoleteColumns),
            'new_columns'      => $newColumns,
            'obsolete_columns' => $obsoleteColumns,
            'altered_columns'  => $alteredColumns];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check for know what columns array returns.
   *
   * @param Columns $newColumns
   * @param Columns $obsoleteColumns
   *
   * @return Columns
   */
  private function getTableColumnsFromConfig($newColumns, $obsoleteColumns)
  {
    $new      = $newColumns->getColumns();
    $obsolete = $obsoleteColumns->getColumns();
    if (!empty($new) && !empty($obsolete))
    {
      return $this->dataTableColumnsConfig;
    }

    return $this->dataTableColumnsDatabase;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create and return trigger name.
   *
   * @param string $action Trigger on action (Insert, Update, Delete)
   *
   * @return string
   */
  private function getTriggerName($action)
  {
    return strtolower(sprintf('trg_%s_%s', $this->alias, $action));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Lock the table to prevent insert, updates, or deletes between dropping and creating triggers.
   *
   * @param string $tableName Name of table
   */
  private function lockTable($tableName)
  {
    DataLayer::lockTable($tableName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logging new and obsolete columns.
   *
   * @param Columns $newColumns
   * @param Columns $obsoleteColumns
   * @param Columns $alteredColumns
   */
  private function loggingColumnInfo($newColumns, $obsoleteColumns, $alteredColumns)
  {
    $new      = $newColumns->getColumns();
    $obsolete = $obsoleteColumns->getColumns();
    if (!empty($new) && !empty($obsolete))
    {
      $this->io->logInfo('Found both new and obsolete columns for table %s', $this->tableName);
      $this->io->logInfo('No action taken');

      /** @var ColumnType $column */
      foreach ($newColumns->getColumns() as $column)
      {
        $this->io->logInfo('New column %s', $column->getProperty('column_name'));
      }
      foreach ($obsoleteColumns->getColumns() as $column)
      {
        $this->io->logInfo('Obsolete column %s', $column->getProperty('column_name'));
      }
    }

    /** @var ColumnType $column */
    foreach ($obsoleteColumns->getColumns() as $column)
    {
      $this->io->logInfo('Obsolete column %s.%s', $this->tableName, $column->getProperty('column_name'));
    }

    /** @var ColumnType $column */
    foreach ($newColumns->getColumns() as $column)
    {
      $this->io->logInfo('New column %s.%s', $this->tableName, $column->getProperty('column_name'));
    }

    foreach ($alteredColumns->getColumns() as $column)
    {
      $this->io->logInfo('Type of <dbo>%s.%s</dbo> has been altered to <dbo>%s</dbo>',
                         $this->tableName,
                         $column['column_name'],
                         $column['column_type']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Releases all table locks.
   */
  private function unlockTables()
  {
    DataLayer::unlockTables();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
