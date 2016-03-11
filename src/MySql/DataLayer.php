<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql;

use Monolog\Logger;
use SetBased\Audit\Exception\ResultException;
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
   * Create trigger for table.
   *
   * @param string $theDataSchema  Database data schema
   * @param string $theAuditSchema Database audit schema
   * @param string $theTableName   Name of table
   * @param string $theAction      Action for trigger {INSERT, UPDATE, DELETE}
   * @param string $theTriggerName Name of trigger
   *
   * @return array
   */
  public static function createTrigger($theDataSchema, $theAuditSchema, $theTableName, $theAction, $theTriggerName)
  {
    if (strcmp($theAction, 'INSERT')!=0)
    {
      if (strcmp($theAction, 'DELETE')!=0)
      {
        $row_state[] = 'OLD';
        $row_state[] = 'NEW';
      }
      else
      {
        $row_state[] = 'OLD';
      }
    }
    else
    {
      $row_state[] = 'NEW';
    }
    $sql     = "
CREATE TRIGGER {$theTriggerName}
AFTER {$theAction} ON `{$theDataSchema}`.`{$theTableName}`
FOR EACH ROW BEGIN
  if (@audit_uuid is null) then
    set @audit_uuid = uuid_short();
  end if;

  if (@abc_g_skip{$theTableName} is null) then
    set @audit_rownum = ifnull(@audit_rownum,0) + 1;

    INSERT INTO `{$theAuditSchema}`.`{$theTableName}`
    VALUES( now()
    ,       ".self::quoteString($theAction)."
    ,       ".self::quoteString($row_state[0])."
    ,       @audit_uuid
    ,       @audit_rownum
    ,       @abc_g_ses_id
    ,       @abc_g_usr_id";
    $columns = self::getTableColumns($theDataSchema, $theTableName);
    foreach ($columns as $column)
    {
      $sql .= ",{$row_state[0]}.`{$column['column_name']}`";
    }
    $sql .= ");";
    if (strcmp($theAction, "UPDATE")==0)
    {
      $sql .= "
    INSERT INTO `{$theAuditSchema}`.`{$theTableName}`
    VALUES( now()
    ,       ".self::quoteString($theAction)."
    ,       ".self::quoteString($row_state[1])."
    ,       @audit_uuid
    ,       @audit_rownum
    ,       @abc_g_ses_id
    ,       @abc_g_usr_id";
      foreach ($columns as $column)
      {
        $sql .= ",{$row_state[1]}.`{$column['column_name']}`";
      }
      $sql .= ");";
    }
    $sql .= "end if;
END;
";

    return self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Acquires a write lock on a table.
   *
   * @param string $theTableName The table name.
   */
  public static function lockTable($theTableName)
  {
    $sql = "LOCK TABLES `{$theTableName}` WRITE";

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Releases all table locks.
   */
  public static function unlockTables()
  {
    $sql = "UNLOCK TABLES";

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Select all trigger for table.
   *
   * @param string $theTriggerName Name of trigger
   *
   * @return array
   */
  public static function dropTrigger($theTriggerName)
  {
    $sql = "DROP TRIGGER `{$theTriggerName}`";

    return self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Generate SQL code for creating table and execute it
   *
   * @param string $theAuditSchema   Database audit schema
   * @param string $theTableName     Name of table
   * @param array  $theMergedColumns Merged columns from table in data schema and columns for audit schema
   *
   * @return string SQL code for creating table
   */
  public static function generateSqlCreateStatement($theAuditSchema, $theTableName, $theMergedColumns)
  {
    $sql_create = "CREATE TABLE `{$theAuditSchema}`.`{$theTableName}` (";
    foreach ($theMergedColumns as $column)
    {
      $sql_create .= '`'.$column['name'].'` '.$column['type'];
      if (end($theMergedColumns)!==$column)
      {
        $sql_create .= ",";
      }
    }
    $sql_create .= ");";

    self::executeNone($sql_create);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all triggers on a table.
   *
   * @param string $theDataSchema The table schema.
   * @param string $theTableName  The table name.
   *
   * @return array
   */
  public static function getTableTriggers($theDataSchema, $theTableName)
  {
    $sql = '
SELECT
  Trigger_Name
FROM
	information_schema.TRIGGERS
WHERE
	TRIGGER_SCHEMA = '.self::quoteString($theDataSchema).'
  AND
  EVENT_OBJECT_TABLE = '.self::quoteString($theTableName);

    return self::executeRows($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Select all table names in a schema.
   *
   * @param string $theSchemaName Name of database
   *
   * @return array
   */
  public static function getTablesNames($theSchemaName)
  {
    $sql = '
select TABLE_NAME AS table_name
from   information_schema.TABLES
where  TABLE_SCHEMA = '.self::quoteString($theSchemaName).'
and    TABLE_TYPE   = "BASE TABLE"
ORDER BY TABLE_NAME';

    return self::executeRows($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @param string $theQuery The SQL statement.
   *
   * @return int The number of affected rows (if any).
   */
  public static function executeNone($theQuery)
  {
    self::$myLog->addDebug("Executing query: $theQuery");

    return parent::executeNone($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Select all columns from table in a schema.
   *
   * @param string $theSchemaName Name of database
   * @param string $theTableName  Name of table
   *
   * @return array
   */
  public static function getTableColumns($theSchemaName, $theTableName)
  {
    $sql = '
select COLUMN_NAME as column_name
,      COLUMN_TYPE as data_type
from   information_schema.COLUMNS
where  TABLE_SCHEMA = '.self::quoteString($theSchemaName).'
and    TABLE_NAME   = '.self::quoteString($theTableName).'
order by COLUMN_NAME';

    return self::executeRows($sql);
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
    self::$myLog->addDebug("Executing query: $theQuery");

    return parent::executeLog($theQuery);
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
    self::$myLog->addDebug("Executing query: $theQuery");

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
    self::$myLog->addDebug("Executing query: $theQuery");

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
    self::$myLog->addDebug("Executing query: $theQuery");

    return parent::executeRows($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a query and shows the data in a formatted in a table (like mysql's default pager) of in multiple tables
   * (in case of a multi query).
   *
   * @param string $theQuery The query.
   *
   * @return int The total number of rows in the tables.
   */
  public static function executeTable($theQuery)
  {
    self::$myLog->addDebug("Executing query: $theQuery");

    return parent::executeTable($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes multiple SQL statements.
   *
   * Wrapper around [multi_mysqli::query](http://php.net/manual/mysqli.multi-query.php), however on failure an exception
   * is thrown.
   *
   * @param string $theQueries The SQL statements.
   *
   * @return \mysqli_result
   */
  public static function multi_query($theQueries)
  {
    self::$myLog->addDebug("Executing query: $theQueries");

    return parent::multi_query($theQueries);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes an SQL statement.
   *
   * Wrapper around [mysqli::query](http://php.net/manual/mysqli.query.php), however on failure an exception is thrown.
   *
   * @param string $theQuery The SQL statement.
   *
   * @return \mysqli_result
   */
  public static function query($theQuery)
  {
    self::$myLog->addDebug("Executing query: $theQuery");

    return parent::query($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
