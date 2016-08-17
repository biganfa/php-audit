<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql\RenameColumn;

use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Audit\Test\MySql\AuditTestCase;
use SetBased\Stratum\MySql\Exception\DataLayerException;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for running audit with a new table column.
 */
class RenameColumnTest extends AuditTestCase
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

    // Insert a row into TABLE1.
    StaticDataLayer::query('insert into `TABLE1`(c1, c2, c3, c4) values(1, 2, 3, 4)');

    // Rename column c3 and d3.
    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/config/rename_column.sql'));

    $status = $this->runAudit();

    $this->assertSame(1, $status);
  }

  //--------------------------------------------------------------------------------------------------------------------
  public function test02()
  {
    try
    {
      StaticDataLayer::query('insert into `TABLE1`(c1, c2, c4) values(1, 2, 4)');
    }
    catch (DataLayerException $e)
    {
      $this->assertContains('Unknown column', $e->getMessage());
      $this->assertContains('c3', $e->getMessage());
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  public function test03()
  {
    try
    {
      StaticDataLayer::query('update `TABLE1` set c1=10, c2=20, c4=40');
    }
    catch (DataLayerException $e)
    {
      $this->assertContains('Unknown column', $e->getMessage());
      $this->assertContains('c3', $e->getMessage());
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  public function test04()
  {
    try
    {
      StaticDataLayer::query('delete from `TABLE1`');
    }
    catch (DataLayerException $e)
    {
      $this->assertContains('Unknown column', $e->getMessage());
      $this->assertContains('c3', $e->getMessage());
    }
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

    return $commandTester->getStatusCode();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
