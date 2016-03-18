<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql;

use Monolog\Logger;
use SetBased\Audit\Exception\FallenException;
use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * CLass for generating dynamically abd executing SQL statements.
 */
class DataLayer extends StaticDataLayer
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logger.
   *
   * @var Logger
   */
  private static $ourLogger;

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
    ,       ".self::quoteString($theAction).'
    ,       '.self::quoteString($row_state[0]).'
    ,       @audit_uuid
    ,       @audit_rownum
    ,       @abc_g_ses_id
    ,       @abc_g_usr_id';
    $columns = self::getTableColumns($theDataSchema, $theTableName);
    foreach ($columns as $column)
    {
      $sql .= ",{$row_state[0]}.`{$column['column_name']}`";
    }
    $sql .= ");";

    if ($theAction=="UPDATE")
    {
      $sql .= "
    INSERT INTO `{$theAuditSchema}`.`{$theTableName}`
    VALUES( now()
    ,       ".self::quoteString($theAction).'
    ,       '.self::quoteString($row_state[1]).'
    ,       @audit_uuid
    ,       @audit_rownum
    ,       @abc_g_ses_id
    ,       @abc_g_usr_id';
      foreach ($columns as $column)
      {
        $sql .= ",{$row_state[1]}.`{$column['column_name']}`";
      }
      $sql .= ');';
    }

    $sql .= 'end if;
END;
';

    return self::executeNone($sql);
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
   * {@inheritdoc}
   */
  public static function executeLog($theQuery)
  {
    self::$ourLogger->addDebug("Executing query: $theQuery");

    return parent::executeLog($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  public static function executeNone($theQuery)
  {
    self::$ourLogger->addDebug("Executing query: $theQuery");

    return parent::executeNone($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  public static function executeRow0($theQuery)
  {
    self::$ourLogger->addDebug("Executing query: $theQuery");

    return parent::executeRow0($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  public static function executeRow1($theQuery)
  {
    self::$ourLogger->addDebug("Executing query: $theQuery");

    return parent::executeRow1($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  public static function executeRows($theQuery)
  {
    self::$ourLogger->addDebug("Executing query: $theQuery");

    return parent::executeRows($theQuery);
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
        $sql_create .= ',';
      }
    }
    $sql_create .= ');';

    self::executeNone($sql_create);
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
SELECT COLUMN_NAME AS column_name
,      COLUMN_TYPE AS data_type
FROM   information_schema.COLUMNS
WHERE  TABLE_SCHEMA = '.self::quoteString($theSchemaName).'
AND    TABLE_NAME   = '.self::quoteString($theTableName).'
ORDER BY COLUMN_NAME';

    return self::executeRows($sql);
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
SELECT TABLE_NAME AS table_name
FROM   information_schema.TABLES
WHERE  TABLE_SCHEMA = '.self::quoteString($theSchemaName).'
AND    TABLE_TYPE   = "BASE TABLE"
ORDER BY TABLE_NAME';

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
    $sql = "LOCK TABLES `{$theTableName}` WRITE";

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
    self::$ourLogger = $theLogger;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Releases all table locks.
   */
  public static function unlockTables()
  {
    $sql = 'UNLOCK TABLES';

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
