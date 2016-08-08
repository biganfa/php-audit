<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql\TableOptions;

use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Audit\MySql\DataLayer;
use SetBased\Audit\Test\MySql\AuditTestCase;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for preservation of table options.
 */
class TableOptionsTest extends AuditTestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Connects to the MySQL server.
   */
  public static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();

    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/config/setup.sql'));
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
                             'config file' => __DIR__.'/config/audit.json']);

    $this->assertSame(0, $commandTester->getStatusCode());

    // Reconnect to DB.
    DataLayer::connect('localhost', 'test', 'test', self::$dataSchema);

    $table1_data = DataLayer::getTableOptions('test_data', 'TABLE1');
    $table1_audit = DataLayer::getTableOptions('test_audit', 'TABLE1');
    $this->assertEquals($table1_data, $table1_audit, 'TABLE1');

    $table1_data = DataLayer::getTableOptions('test_data', 'TABLE2');
    $table1_audit = DataLayer::getTableOptions('test_audit', 'TABLE2');
    $this->assertEquals($table1_data, $table1_audit, 'TABLE2');

    $table1_data = DataLayer::getTableOptions('test_data', 'TABLE3');
    $table1_audit = DataLayer::getTableOptions('test_audit', 'TABLE3');
    $this->assertEquals($table1_data, $table1_audit, 'TABLE3');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
