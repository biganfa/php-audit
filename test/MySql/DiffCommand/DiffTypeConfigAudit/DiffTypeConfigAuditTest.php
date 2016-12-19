<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql\DiffCommand\DiffTypeConfigAudit;

use SetBased\Audit\MySql\AuditDataLayer;
use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Audit\MySql\Command\DiffCommand;
use SetBased\Audit\Test\MySql\AuditTestCase;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for running diff with a new table column in audit table.
 */
class DiffTypeConfigAuditTest extends AuditTestCase
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
    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/config/change_column_type.sql'));

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
    $this->assertContains('| c4     | int(11)    | varchar(20)              | int(11) |', $output, 'acquire');
    $this->assertContains('|        |            | [utf8] [utf8_general_ci] |         |', $output, 'acquire');

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
    $command->setRewriteConfigFile(false);
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
