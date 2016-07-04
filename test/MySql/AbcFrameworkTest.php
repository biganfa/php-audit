<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql;

use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for/with typical config for ABC Framework.
 */
class AbcFrameworkTest extends AuditTestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Connects to the MySQL server.
   */
  public static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();

    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/AbcFrameworkTest/setup.sql'));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test audit table is created correctly.
   */
  public function test01()
  {
    $application = new Application();
    $application->add(new AuditCommand());

    /** @var AuditCommand $command */
    $command = $application->find('audit');
    $command->setRewriteConfigFile(false);
    $commandTester = new CommandTester($command);
    $commandTester->execute(['command'     => $command->getName(),
                             'config file' => 'test/MySql/AbcFrameworkTest/audit.json']);

    $this->assertSame(0, $commandTester->getStatusCode());

    // Reconnect to DB.
    StaticDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);

    $sql = sprintf('
select COLUMN_NAME        as column_name
,      COLUMN_TYPE        as column_type
,      IS_NULLABLE        as is_nullable
,      CHARACTER_SET_NAME as character_set_name
,      COLLATION_NAME     as collation_name
from   information_schema.COLUMNS
where  TABLE_SCHEMA = %s
and    TABLE_NAME   = %s
order by ORDINAL_POSITION',
                   StaticDataLayer::quoteString(self::$auditSchema),
                   StaticDataLayer::quoteString('AUT_COMPANY'));

    $rows = StaticDataLayer::executeRows($sql);

    $expected = [['column_name'        => 'audit_timestamp',
                  'column_type'        => 'timestamp',
                  'is_nullable'        => 'NO',
                  'character_set_name' => null,
                  'collation_name'     => null],
                 ['column_name'        => 'audit_statement',
                  'column_type'        => "enum('INSERT','DELETE','UPDATE')",
                  'is_nullable'        => 'NO',
                  'character_set_name' => 'utf8',
                  'collation_name'     => 'utf8_general_ci'],
                 ['column_name'        => 'audit_type',
                  'column_type'        => "enum('OLD','NEW')",
                  'is_nullable'        => 'NO',
                  'character_set_name' => 'utf8',
                  'collation_name'     => 'utf8_general_ci'],
                 ['column_name'        => 'audit_uuid',
                  'column_type'        => 'bigint(20) unsigned',
                  'is_nullable'        => 'NO',
                  'character_set_name' => null,
                  'collation_name'     => null],
                 ['column_name'        => 'audit_rownum',
                  'column_type'        => 'int(10) unsigned',
                  'is_nullable'        => 'NO',
                  'character_set_name' => null,
                  'collation_name'     => null],
                 ['column_name'        => 'audit_ses_id',
                  'column_type'        => 'int(10) unsigned',
                  'is_nullable'        => 'YES',
                  'character_set_name' => null,
                  'collation_name'     => null],
                 ['column_name'        => 'audit_usr_id',
                  'column_type'        => 'int(10) unsigned',
                  'is_nullable'        => 'YES',
                  'character_set_name' => null,
                  'collation_name'     => null],
                 ['column_name'        => 'cmp_id',
                  'column_type'        => 'smallint(5) unsigned',
                  'is_nullable'        => 'YES',
                  'character_set_name' => null,
                  'collation_name'     => null],
                 ['column_name'        => 'cmp_abbr',
                  'column_type'        => 'varchar(15)',
                  'is_nullable'        => 'YES',
                  'character_set_name' => 'utf8',
                  'collation_name'     => 'utf8_general_ci'],
                 ['column_name'        => 'cmp_label',
                  'column_type'        => 'varchar(20)',
                  'is_nullable'        => 'YES',
                  'character_set_name' => 'utf8',
                  'collation_name'     => 'utf8_general_ci']];

    $this->assertEquals($expected, $rows);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test insert trigger is working correctly.
   */
  public function test02a()
  {
    // Insert a row into AUT_COMPANY.
    $sql = sprintf('
insert into `AUT_COMPANY`(`cmp_abbr`
,                         `cmp_label`)
values( %s
,       %s )',
                   StaticDataLayer::quoteString('SYS'),
                   StaticDataLayer::quoteString('SYS'));

    StaticDataLayer::executeNone($sql);

    // Get audit rows.
    $sql = sprintf("
select * 
from   `test_audit`.`AUT_COMPANY`
where  `audit_statement` = 'INSERT'");

    StaticDataLayer::query("SET time_zone = 'Europe/Amsterdam'");
    $rows = StaticDataLayer::executeRows($sql);

    // We expect 1 row.
    $this->assertEquals(1, count($rows));
    $row = $rows[0];

    // Tests on fields.
    $time = new \DateTime();
    $this->assertLessThanOrEqual(date_format($time->add(new \DateInterval('P1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $this->assertGreaterThanOrEqual(date_format($time->sub(new \DateInterval('P1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $this->assertEquals('NEW', $row['audit_type']);
    $this->assertNotEmpty($row['audit_uuid']);
    $this->assertEquals(1, $row['audit_rownum']);
    $this->assertNull($row['audit_ses_id']);
    $this->assertNull($row['audit_usr_id']);
    $this->assertEquals('1', $row['cmp_id']);
    $this->assertEquals('SYS', $row['cmp_abbr']);
    $this->assertEquals('SYS', $row['cmp_label']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test update trigger is working correctly.
   */
  public function test02b()
  {
    // Set session and user ID.
    StaticDataLayer::executeNone('set @abc_g_ses_id=12345');  // The combination of my suitcase.
    StaticDataLayer::executeNone('set @abc_g_usr_id=7011');

    // Update a row into AUT_COMPANY.
    $sql = sprintf('
update `AUT_COMPANY`
set   `cmp_label` = %s
where `cmp_abbr` = %s',
                   StaticDataLayer::quoteString('CMP_ID_SYS'),
                   StaticDataLayer::quoteString('SYS'));

    StaticDataLayer::executeNone($sql);

    // Get audit rows.
    $sql = sprintf("
select * 
from   `test_audit`.`AUT_COMPANY`
where  `audit_statement` = 'UPDATE'");

    StaticDataLayer::query("SET time_zone = 'Europe/Amsterdam'");
    $rows = StaticDataLayer::executeRows($sql);

    // We expect 2 rows.
    $this->assertEquals(2, count($rows));

    // Tests on 'OLD' fields.
    $row  = $rows[StaticDataLayer::searchInRowSet('audit_type', 'OLD', $rows)];
    $time = new \DateTime();
    $this->assertLessThanOrEqual(date_format($time->add(new \DateInterval('P1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $this->assertGreaterThanOrEqual(date_format($time->sub(new \DateInterval('P1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $this->assertEquals('OLD', $row['audit_type']);
    $this->assertNotEmpty($row['audit_uuid']);
    $this->assertSame('2', $row['audit_rownum']);
    $this->assertSame('12345', $row['audit_ses_id']);
    $this->assertSame('7011', $row['audit_usr_id']);
    $this->assertEquals('1', $row['cmp_id']);
    $this->assertEquals('SYS', $row['cmp_abbr']);
    $this->assertEquals('SYS', $row['cmp_label']);

    // Tests on 'NEW' fields.
    $row  = $rows[StaticDataLayer::searchInRowSet('audit_type', 'NEW', $rows)];
    $time = new \DateTime();
    $this->assertLessThanOrEqual(date_format($time->add(new \DateInterval('P1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $this->assertGreaterThanOrEqual(date_format($time->sub(new \DateInterval('P1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $this->assertEquals('NEW', $row['audit_type']);
    $this->assertNotEmpty($row['audit_uuid']);
    $this->assertSame('2', $row['audit_rownum']);
    $this->assertSame('12345', $row['audit_ses_id']);
    $this->assertSame('7011', $row['audit_usr_id']);
    $this->assertEquals('1', $row['cmp_id']);
    $this->assertEquals('SYS', $row['cmp_abbr']);
    $this->assertEquals('CMP_ID_SYS', $row['cmp_label']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test delete trigger is working correctly.
   */
  public function test02c()
  {
    // Delete a row from AUT_COMPANY.
    $sql = sprintf('
delete from `AUT_COMPANY`
where `cmp_abbr` = %s',
                   StaticDataLayer::quoteString('SYS'));

    StaticDataLayer::executeNone($sql);

    // Get audit rows.
    $sql = sprintf("
select * 
from   `test_audit`.`AUT_COMPANY`
where  audit_statement = 'DELETE'");

    StaticDataLayer::query("SET time_zone = 'Europe/Amsterdam'");
    $rows = StaticDataLayer::executeRows($sql);

    // We expect 1 row.
    $this->assertEquals(1, count($rows));
    $row = $rows[0];

    // Tests on fields.
    $time = new \DateTime();
    $this->assertLessThanOrEqual(date_format($time->add(new \DateInterval('PT1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $time = new \DateTime();
    $this->assertGreaterThanOrEqual(date_format($time->sub(new \DateInterval('PT1M')), 'Y-m-d H:i:s'), $row['audit_timestamp']);
    $this->assertEquals('OLD', $row['audit_type']);
    $this->assertNotEmpty($row['audit_uuid']);
    $this->assertEquals(3, $row['audit_rownum']);
    $this->assertSame('12345', $row['audit_ses_id']);
    $this->assertSame('7011', $row['audit_usr_id']);
    $this->assertEquals('1', $row['cmp_id']);
    $this->assertEquals('SYS', $row['cmp_abbr']);
    $this->assertEquals('CMP_ID_SYS', $row['cmp_label']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test total number of rows in audit table.
   */
  public function test02d()
  {
    // Get all audit rows.
    $sql = sprintf("
select * 
from   `test_audit`.`AUT_COMPANY`");

    $rows = StaticDataLayer::executeRows($sql);

    // We expect 4 rows: 1 insert, 2 update, and 1 delete.
    $this->assertEquals(4, count($rows));
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
