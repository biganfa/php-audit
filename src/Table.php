<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

use Monolog\Logger;
use SetBased\Audit\MySql\DataLayer;

//--------------------------------------------------------------------------------------------------------------------
/**
 * Class Table For work with single table
 */
class Table
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Name of table
   *
   * @var string
   */
  private $myTableName;

  /**
   * Monolog
   *
   * @var Logger
   */
  private $myLog;

  /**
   * Data Schema
   *
   * @var string
   */
  private $myDataSchema;

  /**
   * Audit Schema
   *
   * @var string
   */
  private $myAuditSchema;

  /**
   * Merged columns array, audit columns and table columns
   *
   * @var array[]
   */
  private $myTargetColumnsMetadata;

  /**
   * Audit columns from config file
   *
   * @var array[]
   */
  private $myAuditColumnsMetadata;

  /**
   * Columns from config file
   *
   * @var array[]
   */
  private $myConfigColumnsMetadata;

  /**
   * Columns from data schema
   *
   * @var array[]
   */
  private $myCurrentColumnsMetadata;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Table constructor.
   *
   * @param string  $theTableName             Name of table
   * @param Logger  $theLog                   Monolog
   * @param string  $theDataSchema            Data schema
   * @param string  $theAuditSchema           Audit schema
   * @param array[] $theConfigColumnsMetadata Columns from config file
   * @param array[] $theAuditColumnsMetadata  Audit columns from config file
   */
  public function __construct($theTableName,
                              $theLog,
                              $theDataSchema,
                              $theAuditSchema,
                              $theConfigColumnsMetadata,
                              $theAuditColumnsMetadata)
  {
    $this->myTableName              = $theTableName;
    $this->myConfigColumnsMetadata  = new Columns($theConfigColumnsMetadata);
    $this->myLog                    = $theLog;
    $this->myDataSchema             = $theDataSchema;
    $this->myAuditSchema            = $theAuditSchema;
    $this->myCurrentColumnsMetadata = new Columns($this->columnsOfTable());
    $this->myAuditColumnsMetadata   = new Columns($theAuditColumnsMetadata);
    $this->myTargetColumnsMetadata  = Columns::combine($this->myAuditColumnsMetadata,
                                                       $this->myCurrentColumnsMetadata);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main function for work with table
   *
   * @return array[] Columns for config file
   */
  public function main()
  {
    $compared_columns = [];
    if (isset($this->myConfigColumnsMetadata))
    {
      $compared_columns = $this->compareTableColumnsConfig();
    }
    $this->createTriggers();

    if (empty($compared_columns))
    {
      return $this->myCurrentColumnsMetadata->getColumns();
    }
    else
    {
      return $this->myConfigColumnsMetadata->getColumns();
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get current columns metadata
   *
   * @return array[]
   */
  public function getCurrentColumnsMetadata()
  {
    return $this->myCurrentColumnsMetadata;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get table name
   *
   * @return string
   */
  public function getTableName()
  {
    return $this->myTableName;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates triggers for all tables in data schema if need audit this tables in config file.
   */
  public function createTriggers()
  {
    // Lock the table to prevent insert, updates, or deletes between dropping and creating triggers.
    $this->lockTable($this->myTableName);

    // Drop all triggers, if any.
    $this->dropTriggers($this->myDataSchema, $this->myTableName);

    // Create or recreate the audit triggers.
    $this->createTableTrigger($this->myTableName, 'INSERT');
    $this->createTableTrigger($this->myTableName, 'UPDATE');
    $this->createTableTrigger($this->myTableName, 'DELETE');

    // Insert, updates, and deletes are no audited again. So, release lock on the table.
    $this->unlockTables();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Drop trigger from table.
   *
   * @param string $theDataSchema Database data schema
   * @param string $theTableName  Name of table
   */
  public function dropTriggers($theDataSchema, $theTableName)
  {
    $old_triggers = DataLayer::getTableTriggers($theDataSchema, $theTableName);

    foreach ($old_triggers as $trigger)
    {
      $this->logVerbose(sprintf('Drop trigger %s for table %s.', $trigger['Trigger_Name'], $theTableName));
      DataLayer::dropTrigger($trigger['Trigger_Name']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compare columns from table in data_schema with columns in config file
   */
  public function compareTableColumnsConfig()
  {
    $complete_columns = $this->myConfigColumnsMetadata->getColumns();
    $audit_columns    = DataLayer::getTableColumns($this->myAuditSchema, $this->myTableName);
    $new_columns      = Columns::notInOtherSet($this->myCurrentColumnsMetadata->getColumns(), $audit_columns);
    $obsolete_columns = Columns::notInOtherSet($complete_columns, $this->myCurrentColumnsMetadata->getColumns());

    if (!empty($new_columns) && !empty($obsolete_columns))
    {
      $this->logInfo(sprintf('Found both new and obsolete columns.'));
      $this->logInfo(sprintf('No action taken.'));
      foreach ($new_columns as $column)
      {
        $this->logInfo(sprintf('Found new column %s.', $column['column_name']));
      }
      foreach ($obsolete_columns as $column)
      {
        $this->logInfo(sprintf('Found obsolete column %s.', $column['column_name']));
      }

      return $complete_columns;
    }

    foreach ($obsolete_columns as $column)
    {
      $this->logInfo(sprintf('Column %s not longer in table %s', $column['column_name'], $this->myTableName));
      $key = DataLayer::searchInRowSet('column_name', $column['column_name'], $complete_columns);
      if (isset($key))
      {
        unset($complete_columns, $key);
      }
    }

    foreach ($new_columns as $new_column)
    {
      $this->logInfo(sprintf('Found new column %s in table %s', $new_column['column_name'], $this->myTableName));
      $complete_columns[] = ['column_name' => $new_column['column_name'],
                             'column_type' => $new_column['column_type']];
    }
    $this->addNewColumns($this->myAuditSchema, $new_columns, 'audit_usr_id');

    return $complete_columns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add new columns to audit table if table does not have
   *
   * @param string  $theDataSchema  The table schema.
   * @param array[] $theColumns     Columns array
   * @param string  $theAfterColumn After which column add new columns
   */
  public function addNewColumns($theDataSchema, $theColumns, $theAfterColumn)
  {
    DataLayer::addNewColumns($theDataSchema, $this->myTableName, $theColumns, $theAfterColumn);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create missing table in audit schema
   */
  public function createMissingAuditTable()
  {
    $this->logInfo(sprintf('Creating audit table %s.', $this->myTableName));
    DataLayer::generateSqlCreateStatement($this->myAuditSchema, $this->myTableName, $this->myTargetColumnsMetadata->getColumns());
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting columns names and data_types from table from information_schema of database from config file.
   *
   * @return array[]
   */
  public function columnsOfTable()
  {
    $result = DataLayer::getTableColumns($this->myDataSchema, $this->myTableName);

    return $result;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create SQL code for trigger for all tables
   *
   * @param string $theTableName The name of table
   * @param string $theAction    Trigger ON action {INSERT, DELETE, UPDATE}
   */
  public function createTableTrigger($theTableName, $theAction)
  {
    $this->logVerbose(sprintf('Create %s trigger for table %s.', $theAction, $theTableName));
    $trigger_name = $this->getTriggerName($this->myDataSchema, $theAction);
    DataLayer::createTrigger($this->myDataSchema,
                             $this->myAuditSchema,
                             $theTableName,
                             $theAction,
                             $trigger_name);
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
  public function getTriggerName($theDataSchema, $theAction)
  {
    $uuid = uniqid('trg_');

    return strtolower(sprintf('`%s`.`%s_%s`', $theDataSchema, $uuid, $theAction));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Lock the table to prevent insert, updates, or deletes between dropping and creating triggers.
   *
   * @param string $theTableName Name of table
   */
  public function lockTable($theTableName)
  {
    DataLayer::lockTable($theTableName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Insert, updates, and deletes are no audited again. So, release lock on the table.
   */
  public function unlockTables()
  {
    DataLayer::unlockTables();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Log function
   *
   * @param string $theMessage Message for print in console
   */
  public function logInfo($theMessage)
  {
    $this->myLog->addNotice($theMessage);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Log verbose
   *
   * @param string $theMessage Message for print in console
   */
  public function logVerbose($theMessage)
  {
    $this->myLog->addInfo($theMessage);
  }

  //--------------------------------------------------------------------------------------------------------------------
}
