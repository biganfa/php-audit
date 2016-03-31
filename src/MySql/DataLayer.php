<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql;

use Monolog\Logger;
use SetBased\Stratum\Exception\FallenException;
use SetBased\Stratum\Exception\ResultException;
use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Supper class for a static stored routine wrapper class.
 */
class DataLayer extends StaticDataLayer
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logger.
   *
   * @var Logger
   */
  private static $myLog;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Adds new columns to an audit table.
   *
   * @param string $theAuditSchemaName The name of audit schema.
   * @param string $theTableName       The name of the table.
   * @param array  $theColumns         The metadata of the new columns.
   * @param string $theAfterColumn     After which column add new columns.
   */
  public static function addNewColumns($theAuditSchemaName, $theTableName, $theColumns, $theAfterColumn)
  {
    $sql = sprintf('alter table `%s`.`%s`', $theAuditSchemaName, $theTableName);
    foreach ($theColumns as $column)
    {
      $sql .= 'add `'.$column['column_name'].'` '.$column['column_type'].' after `'.$theAfterColumn.'`';
      if (end($theColumns)!==$column)
      {
        $sql .= ',';
      }
    }

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a trigger on a table.
   *
   * @param string $theDataSchema      The name of the data schema.
   * @param string $theAuditSchemaName The name of the audit schema.
   * @param string $theTableName       The name of the table.
   * @param string $theAction          Action for trigger {INSERT, UPDATE, DELETE}
   * @param string $theTriggerName     The name of the trigger.
   */
  public static function createTrigger($theDataSchema, $theAuditSchemaName, $theTableName, $theAction, $theTriggerName)
  {
    $row_state = [];
    switch ($theAction)
    {
      case 'INSERT':
        $row_state[] = 'NEW';
        break;

      case 'DELETE':
        $row_state[] = 'OLD';
        break;

      case 'UPDATE':
        $row_state[] = 'OLD';
        $row_state[] = 'NEW';
        break;

      default:
        throw new FallenException('$theAction', $theAction);
    }

    $sql = sprintf('
create trigger %s
after %s on `%s`.`%s`
for each row begin
  if (@audit_uuid is null) then
    set @audit_uuid = uuid_short();
  end if;

  if (@abc_g_skip%s is null) then
    set @audit_rownum = ifnull(@audit_rownum,0) + 1;

    insert into `%s`.`%s`
    values( now()
    ,       %s
    ,       %s
    ,       @audit_uuid
    ,       @audit_rownum
    ,       @abc_g_ses_id
    ,       @abc_g_usr_id',
                   $theTriggerName,
                   $theAction,
                   $theDataSchema,
                   $theTableName,
                   $theTableName,
                   $theAuditSchemaName,
                   $theTableName,
                   self::quoteString($theAction),
                   self::quoteString($row_state[0]));

    $columns = self::getTableColumns($theDataSchema, $theTableName);
    foreach ($columns as $column)
    {
      $sql .= sprintf(',%s.`%s`', $row_state[0], $column['column_name']);
    }
    $sql .= ');';

    if ($theAction=='UPDATE')
    {
      $sql .= sprintf('
    insert into `%s`.`%s`
    values( now()
    ,       %s
    ,       %s
    ,       @audit_uuid
    ,       @audit_rownum
    ,       @abc_g_ses_id
    ,       @abc_g_usr_id',
                      $theAuditSchemaName,
                      $theTableName,
                      self::quoteString($theAction),
                      self::quoteString($row_state[1]));
      foreach ($columns as $column)
      {
        $sql .= sprintf(',%s.`%s`', $row_state[1], $column['column_name']);
      }
      $sql .= ');';
    }
    $sql .= 'end if;
end;
';

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Select all trigger for table.
   *
   * @param string $theTriggerName Name of trigger
   */
  public static function dropTrigger($theTriggerName)
  {
    $sql = sprintf('drop trigger `%s`', $theTriggerName);

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a query and logs the result set.
   *
   * @param string $theQuery The query or multi query.
   *
   * @return int The total number of rows selected/logged.
   */
  public static function executeLog($theQuery)
  {
    self::$myLog->addDebug(sprintf('Executing query: %s', $theQuery));

    return parent::executeLog($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @param string $theQuery The SQL statement.
   *
   * @return int The number of affected rows (if any).
   */
  public static function executeNone($theQuery)
  {
    self::$myLog->addDebug(sprintf('Executing query: %s', $theQuery));

    return parent::executeNone($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a query that returns 0 or 1 row.
   * Throws an exception if the query selects 2 or more rows.
   *
   * @param string $theQuery The SQL statement.
   *
   * @return array|null The selected row.
   * @throws ResultException
   */
  public static function executeRow0($theQuery)
  {
    self::$myLog->addDebug(sprintf('Executing query: %s', $theQuery));

    return parent::executeRow0($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a query that returns 1 and only 1 row.
   * Throws an exception if the query selects none, 2 or more rows.
   *
   * @param string $theQuery The SQL statement.
   *
   * @return array The selected row.
   * @throws ResultException
   */
  public static function executeRow1($theQuery)
  {
    self::$myLog->addDebug(sprintf('Executing query: %s', $theQuery));

    return parent::executeRow1($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a query that returns 0 or more rows.
   *
   * @param string $theQuery The SQL statement.
   *
   * @return array[] The selected rows.
   */
  public static function executeRows($theQuery)
  {
    self::$myLog->addDebug(sprintf('Executing query: %s', $theQuery));

    return parent::executeRows($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates an audit table.
   *
   * @param string $theAuditSchemaName The name of the audit schema.
   * @param string $theTableName       The name of the table.
   * @param array  $theMergedColumns   The metadata of the columns of the audit table (i.e. the audit columns and
   *                                   columns of the data table).
   */
  public static function generateSqlCreateStatement($theAuditSchemaName, $theTableName, $theMergedColumns)
  {
    $sql_create = sprintf('create table `%s`.`%s` (', $theAuditSchemaName, $theTableName);
    foreach ($theMergedColumns as $column)
    {
      $sql_create .= sprintf('`%s` %s', $column['column_name'], $column['column_type']);
      if (end($theMergedColumns)!==$column)
      {
        $sql_create .= ',';
      }
    }
    $sql_create .= ')';

    self::executeNone($sql_create);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects metadata of all columns of table.
   *
   * @param string $theSchemaName The name of the table schema.
   * @param string $theTableName  The name of the table.
   *
   * @return array[]
   */
  public static function getTableColumns($theSchemaName, $theTableName)
  {
    $sql = sprintf('
select COLUMN_NAME as column_name
,      COLUMN_TYPE as column_type
from   information_schema.COLUMNS
where  TABLE_SCHEMA = %s
and    TABLE_NAME   = %s
order by COLUMN_NAME',
                   self::quoteString($theSchemaName),
                   self::quoteString($theTableName));

    return self::executeRows($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all triggers on a table.
   *
   * @param string $theSchemaName The name of the table schema.
   * @param string $theTableName  The name of the table.
   *
   * @return array[]
   */
  public static function getTableTriggers($theSchemaName, $theTableName)
  {
    $sql = sprintf('
select
  Trigger_Name
from
	information_schema.TRIGGERS
where
	TRIGGER_SCHEMA = %s
  and
  EVENT_OBJECT_TABLE = %s',
                   self::quoteString($theSchemaName),
                   self::quoteString($theTableName));

    return self::executeRows($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all table names in a schema.
   *
   * @param string $theSchemaName The name of the schema.
   *
   * @return array[]
   */
  public static function getTablesNames($theSchemaName)
  {
    $sql = sprintf("
select TABLE_NAME AS table_name
from   information_schema.TABLES
where  TABLE_SCHEMA = %s
and    TABLE_TYPE   = 'BASE TABLE'
order by TABLE_NAME", self::quoteString($theSchemaName));

    return self::executeRows($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Acquires a write lock on a table.
   *
   * @param string $theTableName The table name.
   */
  public static function lockTable($theTableName)
  {
    $sql = sprintf('lock tables `%s` write', $theTableName);

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for logger.
   *
   * @param Logger $theLogger
   */
  public static function setLog($theLogger)
  {
    self::$myLog = $theLogger;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Releases all table locks.
   */
  public static function unlockTables()
  {
    $sql = 'unlock tables';

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
