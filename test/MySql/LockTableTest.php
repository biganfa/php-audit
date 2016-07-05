<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test\MySql;

use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Tests for table locking.
 */
class LockTableTest extends AuditTestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Connects to the MySQL server.
   */
  public static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();

    StaticDataLayer::multiQuery(file_get_contents(__DIR__.'/LockTableTest/setup.sql'));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test locking table is in verbose output.
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
                             'config file' => 'test/MySql/LockTableTest/audit.json'],
                            ['verbosity' =>
                               OutputInterface::VERBOSITY_NORMAL |
                               OutputInterface::VERBOSITY_VERBOSE |
                               OutputInterface::VERBOSITY_VERY_VERBOSE]);

    $status = $commandTester->getStatusCode();
    $this->assertSame(0, $status, 'status code');

    $output = $commandTester->getDisplay();
    $this->assertContains('lock tables `TABLE1` write', $output, 'acquire');
    $this->assertContains('unlock tables', $output, 'release');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test that the table is actually locked.
   */
  public function test02()
  {
    $application = new Application();
    $application->add(new AuditCommand());

    // Start process that inserts rows into TABLE1.
    $generator = new Process(__DIR__.'/LockTableTest/generator.php');
    $generator->start();
    $pid = $generator->getPid();

    // Give generator some time to startup.

    /** @var AuditCommand $command */
    $command = $application->find('audit');
    $command->setRewriteConfigFile(false);
    $commandTester = new CommandTester($command);
    $commandTester->execute(['command'     => $command->getName(),
                             'config file' => 'test/MySql/LockTableTest/audit.json']);

    // Tell the generator it is time to stop.
    $generator->signal(SIGUSR1);

    $status = $commandTester->getStatusCode();
    $this->assertSame(0, $status, 'status code');

    pcntl_waitpid($pid, $status);
    sleep(5);

    // Reconnect to DB.
    StaticDataLayer::connect('localhost', 'test', 'test', self::$dataSchema);

    $n1 = 0;
    $n2 = 0;
    for ($i = 0; $i<60; $i++)
    {
      $n1 = StaticDataLayer::executeSingleton1("select AUTO_INCREMENT - 1 
                                                from information_schema.TABLES
                                                where TABLE_SCHEMA = 'test_data'
                                                and   TABLE_NAME   = 'TABLE1'");
      $n2 = StaticDataLayer::executeSingleton1('select sum(1) from test_audit.TABLE1');

      if (4 * $n1==$n2) break;

      echo "$n1, ".(4 * $n1).", $n2\n";
      StaticDataLayer::executeTable("select id from test_audit.TABLE1 group by id having count(*)<>4");
      StaticDataLayer::executeTable("select * from test_audit.TABLE1 where id in (select id from test_audit.TABLE1 group by id having count(*)<>4)");
      sleep(1);
    }

    $this->assertEquals(4 * $n1, $n2, 'count');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
