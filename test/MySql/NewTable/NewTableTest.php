<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql\NewTable;

use SetBased\Audit\MySql\AuditDataLayer;
use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Audit\Test\MySql\AuditTestCase;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for running audit with a new table.
 */
class NewTableTest extends AuditTestCase
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
    $this->runAudit();

    // TABLE1 MUST and TABLE2 MUST not exist.
    $tables = $this->getAuditTables();
    $this->assertNotNull(StaticDataLayer::searchInRowSet('table_name', 'TABLE1', $tables));
    $this->assertNull(StaticDataLayer::searchInRowSet('table_name', 'TABLE2', $tables));

    // TABLE1 MUST have triggers.
    $triggers = $this->getTableTriggers('TABLE1');
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_insert', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_update', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_delete', $triggers));

    // Create new table TABLE2.
    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/config/create_new_table.sql'));

    $this->runAudit();

    // TABLE1 and TABLE2 MUST exist.
    $tables = $this->getAuditTables();
    $this->assertNotNull(StaticDataLayer::searchInRowSet('table_name', 'TABLE1', $tables));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('table_name', 'TABLE2', $tables));

    // TABLE1 and TABLE2 MUST have triggers.
    $triggers = $this->getTableTriggers('TABLE1');
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_insert', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_update', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t1_delete', $triggers));
    $triggers = $this->getTableTriggers('TABLE2');
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t2_insert', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t2_update', $triggers));
    $this->assertNotNull(StaticDataLayer::searchInRowSet('trigger_name', 'trg_t2_delete', $triggers));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns all tables in the audit schema.
   *
   * @return \array[]
   */
  private function getAuditTables()
  {
    AuditDataLayer::disconnect();
    AuditDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);
    $tables = AuditDataLayer::getTablesNames(self::$auditSchema);

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
    AuditDataLayer::disconnect();
    AuditDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);
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
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
