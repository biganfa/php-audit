<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql;

use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
class AuditTestCase extends \PHPUnit_Framework_TestCase
{
  protected static $ourAuditSchema = 'test_audit';

  protected static$ourDataSchema = 'test_data';

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Connects to the MySQL server.
   */
  public  static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();

    StaticDataLayer::connect('localhost', 'test', 'test', self::$ourDataSchema);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Drops all tables in test_data and test_audit.
   */
  protected function dropAllTables()
  {
    $sql = "
select TABLE_SCHEMA as table_schema
,      TABLE_NAME   as table_name
from   information_schema.TABLES
where TABLE_SCHEMA in (%s,%s)";

    $sql = sprintf($sql,
                   StaticDataLayer::quoteString(self::$ourDataSchema),
                   StaticDataLayer::quoteString(self::$ourAuditSchema));

    $tables = StaticDataLayer::executeRows($sql);

    foreach($tables as $table)
    {
      $sql = "drop table `%s`.`%s`";
      $sql = sprintf($sql, $table['table_schema'], $table['table_name']);

      echo $sql, "\n";

      StaticDataLayer::executeNone($sql);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
