<?php
//----------------------------------------------------------------------------------------------------------------------
/**
 * PphpStratum
 *
 * @copyright 2005-2015 Paul Water / Set Based IT Consultancy (https://www.setbased.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link
 */
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql;

use SetBased\Audit\Exception\ResultException;
use SetBased\Audit\Exception\RuntimeException;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Supper class for a static stored routine wrapper class.
 */
class DataLayer
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The default character set to be used when sending data from and to the MySQL instance.
   *
   * @var string
   */
  public static $ourCharSet = 'utf8';

  /**
   * The SQL mode of the MySQL instance.
   *
   * @var string
   */
  public static $ourSqlMode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY';

  /**
   * The transaction isolation level. Possible values are:
   * <ul>
   * <li> REPEATABLE-READ
   * <li> READ-COMMITTED
   * <li> READ-UNCOMMITTED
   * <li> SERIALIZABLE
   * </ul>
   *
   * @var string
   */
  public static $ourTransactionIsolationLevel = 'READ-COMMITTED';

  /**
   * Chunk size when transmitting LOB to the MySQL instance. Must be less than max_allowed_packet.
   *
   * @var int
   */
  protected static $ourChunkSize;

  /**
   * True if method mysqli_result::fetch_all exists (i.e. we are using MySQL native driver).
   *
   * @var bool
   */
  protected static $ourHaveFetchAll;

  /**
   * The connection between PHP and the MySQL instance.
   *
   * @var \mysqli
   */
  protected static $ourMySql;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Starts a transaction.
   *
   * Wrapper around [mysqli::autocommit](http://php.net/manual/mysqli.autocommit.php), however on failure an exception
   * is thrown.
   */
  public static function begin()
  {
    $ret = self::$ourMySql->autocommit(false);
    if (!$ret) self::mySqlError('mysqli::autocommit');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Commits the current transaction (and starts a new transaction).
   *
   * Wrapper around [mysqli::commit](http://php.net/manual/mysqli.commit.php), however on failure an exception is
   * thrown.
   */
  public static function commit()
  {
    $ret = self::$ourMySql->commit();
    if (!$ret) self::mySqlError('mysqli::commit');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Connects to a MySQL instance.
   *
   * Wrapper around [mysqli::__construct](http://php.net/manual/mysqli.construct.php), however on failure an exception
   * is thrown.
   *
   * @param string $theHostName The hostname.
   * @param string $theUserName The MySQL user name.
   * @param string $thePassWord The password.
   * @param string $theDatabase The default database.
   * @param int    $thePort     The port number.
   */
  public static function connect($theHostName, $theUserName, $thePassWord, $theDatabase, $thePort = 3306)
  {
    self::$ourMySql = new \mysqli($theHostName, $theUserName, $thePassWord, $theDatabase, $thePort);
    if (self::$ourMySql->connect_errno)
    {
      $message = "MySQL Error no: ".self::$ourMySql->connect_errno."\n";
      $message .= str_replace('%', '%%', self::$ourMySql->connect_error);
      $message .= "\n";

      throw new RuntimeException($message);
    }

    // Set the default character set.
    if (self::$ourCharSet)
    {
      $ret = self::$ourMySql->set_charset(self::$ourCharSet);
      if (!$ret) self::mySqlError('mysqli::set_charset');
    }

    // Set the SQL mode.
    if (self::$ourSqlMode)
    {
      self::executeNone("SET sql_mode = '".self::$ourSqlMode."'");
    }

    // Set transaction isolation level.
    if (self::$ourTransactionIsolationLevel)
    {
      self::executeNone("SET SESSION tx_isolation = '".self::$ourTransactionIsolationLevel."'");
    }

    // Set flag to use method mysqli_result::fetch_all if we are using MySQL native driver.
    self::$ourHaveFetchAll = method_exists('mysqli_result', 'fetch_all');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Closes the connection to the MySQL instance, if connected.
   */
  public static function disconnect()
  {
    if (self::$ourMySql)
    {
      self::$ourMySql->close();
      self::$ourMySql = null;
    }
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
    // Counter for the number of rows written/logged.
    $n = 0;

    self::multi_query($theQuery);
    do
    {
      $result = self::$ourMySql->store_result();
      if (self::$ourMySql->errno) self::mySqlError('mysqli::store_result');
      if ($result)
      {
        $fields = $result->fetch_fields();
        while ($row = $result->fetch_row())
        {
          $line = '';
          foreach ($row as $i => $field)
          {
            if ($i>0) $line .= ' ';
            $line .= str_pad($field, $fields[$i]->max_length);
          }
          echo date('Y-m-d H:i:s'), ' ', $line, "\n";
          $n++;
        }
        $result->free();
      }

      $continue = self::$ourMySql->more_results();
      if ($continue)
      {
        $tmp = self::$ourMySql->next_result();
        if ($tmp===false) self::mySqlError('mysqli::next_result');
      }
    } while ($continue);

    return $n;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a query that does not select any rows.
   *
   * @param string $theQuery The SQL statement.
   *
   * @return int The number of affected rows (if any).
   */
  public static function executeNone($theQuery)
  {
    self::query($theQuery);

    $n = self::$ourMySql->affected_rows;

    if (self::$ourMySql->more_results()) self::$ourMySql->next_result();

    return $n;
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
    $result = self::query($theQuery);
    $row    = $result->fetch_assoc();
    $n      = $result->num_rows;
    $result->free();

    if (self::$ourMySql->more_results()) self::$ourMySql->next_result();

    if (!($n==0 || $n==1))
    {
      throw new ResultException('0 or 1', $n, $theQuery);
    }

    return $row;
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
    $result = self::query($theQuery);
    $row    = $result->fetch_assoc();
    $n      = $result->num_rows;
    $result->free();

    if (self::$ourMySql->more_results()) self::$ourMySql->next_result();

    if ($n!=1)
    {
      throw new ResultException('1', $n, $theQuery);
    }

    return $row;
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
    $result = self::query($theQuery);
    if (self::$ourHaveFetchAll)
    {
      $ret = $result->fetch_all(MYSQLI_ASSOC);
    }
    else
    {
      $ret = [];
      while ($row = $result->fetch_assoc())
      {
        $ret[] = $row;
      }
    }
    $result->free();

    if (self::$ourMySql->more_results()) self::$ourMySql->next_result();

    return $ret;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the first row in a row set for which a column has a specific value.
   *
   * Throws an exception if now row is found.
   *
   * @param string  $theColumnName The column name (or in PHP terms the key in an row (i.e. array) in the row set).
   * @param mixed   $theValue      The value to be found.
   * @param array[] $theRowSet     The row set.
   *
   * @return mixed
   */
  public static function getRowInRowSet($theColumnName, $theValue, $theRowSet)
  {
    if (is_array($theRowSet))
    {
      foreach ($theRowSet as $key => $row)
      {
        if ((string)$row[$theColumnName]==(string)$theValue)
        {
          return $row;
        }
      }
    }

    throw new RuntimeException("Value '%s' for column '%s' not found in row set.", $theValue, $theColumnName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Select all columns from table in a schema.
   *
   * @param $theSchemaName string name of database
   *
   * @param $theTableName  string name of table
   *
   * @return array
   */
  public static function getTableColumns($theSchemaName, $theTableName)
  {
    $sql = '
select COLUMN_NAME AS column_name
,      DATA_TYPE AS data_type
from   information_schema.COLUMNS
where  TABLE_SCHEMA = "'.$theSchemaName.'"
and    TABLE_NAME = "'.$theTableName.'"
ORDER BY COLUMN_NAME';

    return self::executeRows($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Select all table names in a schema.
   *
   * @param string $theSchemaName name of database
   *
   * @return array[]
   */
  public static function getTables($theSchemaName)
  {
    $sql = "
select TABLE_NAME AS table_name
from   information_schema.TABLES
where  TABLE_SCHEMA = '%s'
and    TABLE_TYPE   = 'BASE TABLE'
ORDER BY TABLE_NAME";

    $sql = sprintf($sql, self::$ourMySql->real_escape_string($theSchemaName));

    return self::executeRows($sql);
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
    $ret = self::$ourMySql->multi_query($theQueries);
    if ($ret===false) self::mySqlError($theQueries);

    return $ret;
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
    $ret = self::$ourMySql->query($theQuery);
    if ($ret===false) self::mySqlError($theQuery);

    return $ret;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a literal for a bit field that can be safely used in SQL statements.
   *
   * @param string $theBits The bit field.
   *
   * @return string
   */
  public static function quoteBit($theBits)
  {
    if ($theBits===null || $theBits===false || $theBits==='')
    {
      return 'NULL';
    }
    else
    {
      return "b'".self::$ourMySql->real_escape_string($theBits)."'";
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a literal for a numerical field that can be safely used in SQL statements.
   * Throws an exception if the value is not numeric.
   *
   * @param string $theValue The number.
   *
   * @return string
   */
  public static function quoteNum($theValue)
  {
    if (is_numeric($theValue)) return $theValue;
    if ($theValue===null || $theValue==='' || $theValue===false) return 'NULL';
    if ($theValue===true) return 1;

    throw new RuntimeException("Value '%s' is not a number.", $theValue);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a literal for a string field that can be safely used in SQL statements.
   *
   * @param string $theString The string.
   *
   * @return string
   */
  public static function quoteString($theString)
  {
    if ($theString===null || $theString===false || $theString==='')
    {
      return 'NULL';
    }
    else
    {
      return "'".self::$ourMySql->real_escape_string($theString)."'";
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Escapes special characters in a string such that it can be safely used in SQL statements.
   *
   * Wrapper around [mysqli::real_escape_string](http://php.net/manual/mysqli.real-escape-string.php).
   *
   * @param string $theString The string.
   *
   * @return string
   */
  public static function realEscapeString($theString)
  {
    return self::$ourMySql->real_escape_string($theString);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Execute an SQL query.
   *
   * Wrapper around [mysqli::real_query](http://php.net/manual/en/mysqli.real-query.php), however on failure an
   * exception is thrown.
   *
   * @param string $theQuery The SQL statement.
   */
  public static function realQuery($theQuery)
  {
    $ret = self::$ourMySql->real_query($theQuery);
    if ($ret===false) self::mySqlError($theQuery);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Rollbacks the current transaction (and starts a new transaction).
   *
   * Wrapper around [mysqli::rollback](http://php.net/manual/en/mysqli.rollback.php), however on failure an exception
   * is thrown.
   */
  public static function rollback()
  {
    $ret = self::$ourMySql->rollback();
    if (!$ret) self::mySqlError('mysqli::rollback');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the key of the first row in a row set for which a column has a specific value. Returns null if no row is
   * found.
   *
   * @param string  $theColumnName The column name (or in PHP terms the key in an row (i.e. array) in the row set).
   * @param string  $theValue      The value to be found.
   * @param array[] $theRowSet     The row set.
   *
   * @return int|null|string
   */
  public static function searchInRowSet($theColumnName, $theValue, $theRowSet)
  {
    if (is_array($theRowSet))
    {
      foreach ($theRowSet as $key => $row)
      {
        if ((string)$row[$theColumnName]==(string)$theValue)
        {
          return $key;
        }
      }
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logs the warnings of the last executed SQL statement.
   *
   * Wrapper around the SQL statement [show warnings](https://dev.mysql.com/doc/refman/5.6/en/show-warnings.html).
   */
  public static function showWarnings()
  {
    self::executeLog('show warnings');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Throws an exception with error information provided by MySQL/[mysqli](http://php.net/manual/en/class.mysqli.php).
   *
   * This method must called after a method of [mysqli](http://php.net/manual/en/class.mysqli.php) returns an
   * error only.
   *
   * @param string $theText Additional text for the exception message.
   *
   * @throws \RuntimeException
   */
  protected static function mySqlError($theText)
  {
    $message = "MySQL Error no: ".self::$ourMySql->errno."\n";
    $message .= str_replace('%', '%%', self::$ourMySql->error);
    $message .= "\n";
    $message .= str_replace('%', '%%', $theText);
    $message .= "\n";

    throw new RuntimeException($message);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
