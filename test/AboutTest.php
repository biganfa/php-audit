<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Test;

use SetBased\Audit\Application\AuditApplication;
use Symfony\Component\Console\Tester\CommandTester;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Test for about command.
 */
class AboutTest extends \PHPUnit_Framework_TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  public function test01()
  {
    $application = new AuditApplication();

    $command = $application->find('about');
    $commandTester = new CommandTester($command);
    $commandTester->execute(['command'     => $command->getName()]);

    $this->assertSame(0, $commandTester->getStatusCode());
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
