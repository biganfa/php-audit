<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql\DiffCommand\NewColumnDataTableTest;

use SetBased\Audit\MySql\AuditDataLayer;
use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Audit\MySql\Command\DiffCommand;
use SetBased\Audit\Test\MySql\AuditTestCase;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for running diff with a new table column in data table.
 */
class NewColumnDataTableTest extends AuditTestCase
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

    // Create new column.
    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/config/create_new_column.sql'));

    $this->runDiff();    
  }

  //--------------------------------------------------------------------------------------------------------------------
  private function runDiff()
  {
    $application = new Application();
    $application->add(new DiffCommand());

    /** @var DiffCommand $command */
    $command = $application->find('diff');
    $command->setRewriteConfigFile(false);
    $commandTester = new CommandTester($command);
    $commandTester->execute(['command'     => $command->getName(),
                             'config file' => __DIR__.'/config/audit.json']);

    $output = $commandTester->getDisplay();
    $this->assertContains('| c3     |            | mediumint(9) |        |', $output, 'acquire');

    // Reconnect to MySQL.
    StaticDataLayer::disconnect();
    StaticDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);
    AuditDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);
  }

  //--------------------------------------------------------------------------------------------------------------------
  private function runAudit()
  {
    $application = new Application();
    $application->add(new AuditCommand());

    /** @var AuditCommand $command */
    $command = $application->find('audit');
    $command->setRewriteConfigFile(true);
    $commandTester = new CommandTester($command);
    $commandTester->execute(['command'     => $command->getName(),
                             'config file' => __DIR__.'/config/audit.json']);

    // Reconnect to MySQL.
    StaticDataLayer::disconnect();
    StaticDataLayer::connect('localhost', 'test', 'test', self::$auditSchema);
    AuditDataLayer::connect('localhost', 'test', 'test', self::$auditSchema);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
