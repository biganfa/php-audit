<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

use SetBased\Audit\MySql\DataLayer;
use SetBased\Audit\MySql\Sql\CreateAuditTable;
use SetBased\Audit\MySql\Sql\CreateAuditTrigger;
use Symfony\Component\Console\Output\OutputInterface;

//--------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of tables.
 */
class Table
{
  //--------------------------------------------------------------------------------------------------------------------
  use ConsoleOutput;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The unique alias for this data table.
   *
   * @var string
   */
  private $myAlias;

  /**
   * The metadata (additional) audit columns (as stored in the config file).
   *
   * @var Columns
   */
  private $myAuditColumns;

  /**
   * The name of the schema with the audit tables.
   *
   * @var string
   */
  private $myAuditSchema;

  /**
   * The name of the schema with the data tables.
   *
   * @var string
   */
  private $myDataSchema;

  /**
   * The metadata of the columns of the data table as stored in the config file.
   *
   * @var Columns
   */
  private $myDataTableColumnsConfig;

  /**
   * The metadata of the columns of the data table retrieved from information_schema.
   *
   * @var Columns
   */
  private $myDataTableColumnsDatabase;

  /**
   * The skip variable for triggers.
   *
   * @var string
   */
  private $mySkipVariable;

  /**
   * The name of this data table.
   *
   * @var string
   */
  private $myTableName;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param OutputInterface $output                   The output for log messages.
   * @param string          $theTableName             The table name.
   * @param string          $theDataSchema            The name of the schema with data tables.
   * @param string          $theAuditSchema           The name of the schema with audit tables.
   * @param array[]         $theConfigColumnsMetadata The columns of the data table as stored in the config file.
   * @param array[]         $theAuditColumnsMetadata  The columns of the audit table as stored in the config file.
   * @param string          $theAlias                 An unique alias for this table.
   * @param string          $theSkipVariable          The skip variable
   */
  public function __construct($output,
                              $theTableName,
                              $theDataSchema,
                              $theAuditSchema,
                              $theConfigColumnsMetadata,
                              $theAuditColumnsMetadata,
                              $theAlias,
                              $theSkipVariable)
  {
    $this->output                     = $output;
    $this->myTableName                = $theTableName;
    $this->myDataTableColumnsConfig   = new Columns($theConfigColumnsMetadata);
    $this->myDataSchema               = $theDataSchema;
    $this->myAuditSchema              = $theAuditSchema;
    $this->myDataTableColumnsDatabase = new Columns($this->getColumnsFromInformationSchema());
    $this->myAuditColumns             = new Columns($theAuditColumnsMetadata);
    $this->myAlias                    = $theAlias;
    $this->mySkipVariable             = $theSkipVariable;
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
   * Creates missing audit table.
   */
  public function createMissingAuditTable()
  {
    $this->logInfo('Creating audit table <dbo>%s.%s<dbo>', $this->myAuditSchema, $this->myTableName);

    $columns = Columns::combine($this->myAuditColumns, $this->myDataTableColumnsDatabase);
    CreateAuditTable::buildStatement($this->myAuditSchema, $this->myTableName, $columns->getColumns());
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates audit triggers on this table.
   */
  public function createTriggers()
  {
    // Lock the table to prevent insert, updates, or deletes between dropping and creating triggers.
    $this->lockTable($this->myTableName);

    // Drop all triggers, if any.
    $this->dropTriggers();

    // Create or recreate the audit triggers.
    $this->createTableTrigger('INSERT');
    $this->createTableTrigger('UPDATE');
    $this->createTableTrigger('DELETE');

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
    return $this->myTableName;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main function for work with table.
   *
   * @return array[] Columns for config file
   */
  public function main()
  {
    $comparedColumns = null;
    if (isset($this->myDataTableColumnsConfig))
    {
      $comparedColumns = $this->getTableColumnInfo();
    }

    if (empty($comparedColumns['new_columns']) && empty($comparedColumns['obsolete_columns']))
    {
      if (empty($comparedColumns['altered_columns']))
      {
        $this->createTriggers();
      }
    }

    return $comparedColumns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Adds new columns to audit table.
   *
   * @param array[] $theColumns Columns array
   */
  private function addNewColumns($theColumns)
  {
    DataLayer::addNewColumns($this->myAuditSchema, $this->myTableName, $theColumns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a triggers for this table.
   *
   * @param string $theAction Trigger ON action {INSERT, DELETE, UPDATE}
   */
  private function createTableTrigger($theAction)
  {
    $triggerName = $this->getTriggerName($this->myDataSchema, $theAction);

    $this->logVerbose('Creating trigger <dbo>%s</dbo> on table <dbo>%s.%s</dbo>',
                      str_replace('`', '', $triggerName),
                      $this->myDataSchema,
                      $this->myTableName);

    CreateAuditTrigger::buildStatement($this->myDataSchema,
                                       $this->myAuditSchema,
                                       $this->myTableName,
                                       $theAction,
                                       $triggerName,
                                       $this->mySkipVariable,
                                       $this->myDataTableColumnsConfig,
                                       $this->myAuditColumns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Drops all triggers from this table.
   */
  private function dropTriggers()
  {
    $triggers = DataLayer::getTableTriggers($this->myDataSchema, $this->myTableName);
    foreach ($triggers as $trigger)
    {
      $this->logVerbose('Dropping trigger <dbo>%s</dbo> on <dbo>%s.%s</dbo>',
                        $trigger['trigger_name'],
                        $this->myDataSchema,
                        $this->myTableName);

      DataLayer::dropTrigger($this->myDataSchema, $trigger['trigger_name']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares columns types from table in data_schema with columns in config file.
   *
   * @return array[]
   */
  private function getAlteredColumns()
  {
    $alteredColumnsTypes = Columns::differentColumnTypes($this->myDataTableColumnsDatabase,
                                                         $this->myDataTableColumnsConfig);
    foreach ($alteredColumnsTypes as $column)
    {
      $this->logInfo('Type of <dbo>%s.%s</dbo> has been altered to <dbo>%s</dbo>',
                     $this->myTableName,
                     $column['column_name'],
                     $column['column_type']);
    }

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
    $result = DataLayer::getTableColumns($this->myDataSchema, $this->myTableName);

    return $result;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compare columns from table in data_schema with columns in config file
   *
   * @return array[]
   */
  private function getTableColumnInfo()
  {
    $columnActual  = new Columns(DataLayer::getTableColumns($this->myAuditSchema, $this->myTableName));
    $columnsConfig = Columns::combine($this->myAuditColumns, $this->myDataTableColumnsConfig);
    $columnsTarget = Columns::combine($this->myAuditColumns, $this->myDataTableColumnsDatabase);

    $newColumns      = Columns::notInOtherSet($columnsTarget, $columnActual);
    $obsoleteColumns = Columns::notInOtherSet($columnsConfig, $columnsTarget);

    $this->loggingColumnInfo($newColumns, $obsoleteColumns);
    $this->addNewColumns($newColumns);

    return ['full_columns'     => $this->getTableColumnsFromConfig($newColumns, $obsoleteColumns),
            'new_columns'      => $newColumns,
            'obsolete_columns' => $obsoleteColumns,
            'altered_columns'  => $this->getAlteredColumns()];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check for know what columns array returns.
   *
   * @param array[] $theNewColumns
   * @param array[] $theObsoleteColumns
   *
   * @return Columns
   */
  private function getTableColumnsFromConfig($theNewColumns, $theObsoleteColumns)
  {
    if (!empty($theNewColumns) && !empty($theObsoleteColumns))
    {
      return $this->myDataTableColumnsConfig;
    }

    return $this->myDataTableColumnsDatabase;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create and return trigger name.
   *
   * @param string $theDataSchema Database data schema
   * @param string $theAction     Trigger on action (Insert, Update, Delete)
   *
   * @return string
   */
  private function getTriggerName($theDataSchema, $theAction)
  {
    return strtolower(sprintf('`%s`.`trg_%s_%s`', $theDataSchema, $this->myAlias, $theAction));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Lock the table to prevent insert, updates, or deletes between dropping and creating triggers.
   *
   * @param string $theTableName Name of table
   */
  private function lockTable($theTableName)
  {
    DataLayer::lockTable($theTableName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logging new and obsolete columns.
   *
   * @param array[] $theNewColumns
   * @param array[] $theObsoleteColumns
   */
  private function loggingColumnInfo($theNewColumns, $theObsoleteColumns)
  {
    if (!empty($theNewColumns) && !empty($theObsoleteColumns))
    {
      $this->logInfo('Found both new and obsolete columns for table %s', $this->myTableName);
      $this->logInfo('No action taken');
      foreach ($theNewColumns as $column)
      {
        $this->logInfo('New column %s', $column['column_name']);
      }
      foreach ($theObsoleteColumns as $column)
      {
        $this->logInfo('Obsolete column %s', $column['column_name']);
      }
    }

    foreach ($theObsoleteColumns as $column)
    {
      $this->logInfo('Obsolete column %s.%s', $this->myTableName, $column['column_name']);
    }

    foreach ($theNewColumns as $column)
    {
      $this->logInfo('New column %s.%s', $this->myTableName, $column['column_name']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Insert, updates, and deletes are no audited again. So, release lock on the table.
   */
  private function unlockTables()
  {
    DataLayer::unlockTables();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
