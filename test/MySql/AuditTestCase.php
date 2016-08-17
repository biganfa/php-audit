<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql;

use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
class AuditTestCase extends \PHPUnit_Framework_TestCase
{
  protected static $auditSchema = 'test_audit';

  protected static $dataSchema = 'test_data';

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Connects to the MySQL server.
   */
  public static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();

    StaticDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);

    self::dropAllTables();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Drops all tables in test_data and test_audit.
   */
  protected static function dropAllTables()
  {
    $sql = "
select TABLE_SCHEMA as table_schema
,      TABLE_NAME   as table_name
from   information_schema.TABLES
where TABLE_SCHEMA in (%s,%s)";

    $sql = sprintf($sql,
                   StaticDataLayer::quoteString(self::$dataSchema),
                   StaticDataLayer::quoteString(self::$auditSchema));

    $tables = StaticDataLayer::executeRows($sql);

    foreach ($tables as $table)
    {
      $sql = "drop table `%s`.`%s`";
      $sql = sprintf($sql, $table['table_schema'], $table['table_name']);

      StaticDataLayer::executeNone($sql);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  protected function setUp()
  {
    StaticDataLayer::disconnect();
    StaticDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);
  }

  //--------------------------------------------------------------------------------------------------------------------
  protected function tearDown()
  {
    StaticDataLayer::disconnect();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
