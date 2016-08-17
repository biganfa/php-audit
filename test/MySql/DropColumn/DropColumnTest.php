<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql\DropColumn;

use SetBased\Audit\MySql\AuditDataLayer;
use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Audit\Test\MySql\AuditTestCase;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for running audit with a new table column.
 */
class DropColumnTest extends AuditTestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();

    StaticDataLayer::disconnect();
    StaticDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);

    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/config/setup.sql'));
  }

  //--------------------------------------------------------------------------------------------------------------------
  public function test01()
  {
    // Run audit.
    $this->runAudit();

    // TABLE1 MUST exist.
    $tables = $this->getAuditTables();
    $this->assertNotNull(StaticDataLayer::searchInRowSet('table_name', 'TABLE1', $tables));

    // TABLE1 MUST have triggers.
    $triggers = $this->getTableTriggers('TABLE1');
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_insert', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_update', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_delete', $triggers));

    $actual = $this->getTableColumns(self::$auditSchema, 'TABLE1');

    $expected   = [];
    $expected[] = ['column_name'        => 'c1',
                   'column_type'        => 'tinyint(4)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];
    $expected[] = ['column_name'        => 'c2',
                   'column_type'        => 'smallint(6)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];
    $expected[] = ['column_name'        => 'c3',
                   'column_type'        => 'mediumint(9)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];
    $expected[] = ['column_name'        => 'c4',
                   'column_type'        => 'int(11)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];

    $this->assertSame($expected, $actual);

    // Test triggers.
    StaticDataLayer::query('insert into `TABLE1`(c1, c2, c3, c4) values(1, 2, 3, 4)');
    StaticDataLayer::query('update `TABLE1` set c1=10, c2=20, c3=30, c4=40');
    StaticDataLayer::query('delete from `TABLE1`');

    $rows = StaticDataLayer::executeRows(sprintf('select * from `%s`.`TABLE1` where c3 is not null',
                                                 self::$auditSchema));
    $this->assertSame(4, count($rows));

    // Drop column c3.
    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/config/drop_column.sql'));

    $this->runAudit();

    // TABLE1 MUST exist.
    $tables = $this->getAuditTables();
    $this->assertNotNull(StaticDataLayer::searchInRowSet('table_name', 'TABLE1', $tables));

    // TABLE1 MUST have triggers.
    $triggers = $this->getTableTriggers('TABLE1');
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_insert', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_update', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_delete', $triggers));

    // TABLE1 must have column c3.
    $actual = $this->getTableColumns(self::$auditSchema, 'TABLE1');

    $expected   = [];
    $expected[] = ['column_name'        => 'c1',
                   'column_type'        => 'tinyint(4)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];
    $expected[] = ['column_name'        => 'c2',
                   'column_type'        => 'smallint(6)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];
    $expected[] = ['column_name'        => 'c3',
                   'column_type'        => 'mediumint(9)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];
    $expected[] = ['column_name'        => 'c4',
                   'column_type'        => 'int(11)',
                   'is_nullable'        => 'YES',
                   'character_set_name' => null,
                   'collation_name'     => null];

    $this->assertSame($expected, $actual);

    // Test triggers.
    StaticDataLayer::query('insert into `TABLE1`(c1, c2, c4) values(1, 2, 4)');
    StaticDataLayer::query('update `TABLE1` set c1=10, c2=20, c4=40');
    StaticDataLayer::query('delete from `TABLE1`');

    // Assert we 4 rows with c3 is null.
    $rows = StaticDataLayer::executeRows(sprintf('select * from `%s`.`TABLE1` where c3 is null',
                                                 self::$auditSchema));
    $this->assertSame(4, count($rows));

    // Assert we 8 rows in total.
    $rows = StaticDataLayer::executeRows(sprintf('select * from `%s`.`TABLE1`', self::$auditSchema));
    $this->assertSame(8, count($rows));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns all tables in the audit schema.
   *
   * @return \array[]
   */
  private function getAuditTables()
  {
    $tables = AuditDataLayer::getTablesNames(self::$auditSchema);

    return $tables;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects metadata of all columns of table.
   *
   * @param string $schemaName The name of the table schema.
   * @param string $tableName  The name of the table.
   *
   * @return \array[]
   */
  private function getTableColumns($schemaName, $tableName)
  {
    $tables = AuditDataLayer::getTableColumns($schemaName, $tableName);

    return $tables;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns all triggers on a table.
   *
   * @param string $table_name The name of the table.
   *
   * @return \array[]
   */
  private function getTableTriggers($table_name)
  {
    $triggers = AuditDataLayer::getTableTriggers(self::$dataSchema, $table_name);

    return $triggers;
  }

  //--------------------------------------------------------------------------------------------------------------------
  private function runAudit()
  {
    $application = new Application();
    $application->add(new AuditCommand());

    /** @var AuditCommand $command */
    $command = $application->find('audit');
    $command->setRewriteConfigFile(false);
    $commandTester = new CommandTester($command);
    $commandTester->execute(['command'     => $command->getName(),
                             'config file' => __DIR__.'/config/audit.json']);

    // Reconnect to MySQL.
    StaticDataLayer::disconnect();
    StaticDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);
    AuditDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
