<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
trait ConsoleOutput
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The output for this object.
   *
   * @var OutputInterface
   */
  protected $output;

  //--------------------------------------------------------------------------------------------------------------------
  protected function logInfo()
  {
    if ($this->output->getVerbosity()>=OutputInterface::VERBOSITY_NORMAL)
    {
      $args   = func_get_args();
      $format = array_shift($args);

      $this->output->writeln(vsprintf('<info>'.$format.'</info>', $args));
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  protected function logVerbose()
  {
    if ($this->output->getVerbosity()>=OutputInterface::VERBOSITY_VERBOSE)
    {
      $args   = func_get_args();
      $format = array_shift($args);

      $this->output->writeln(vsprintf('<info>'.$format.'</info>', $args));
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  protected function logVeryVerbose()
  {
    if ($this->output->getVerbosity()>=OutputInterface::VERBOSITY_VERY_VERBOSE)
    {
      $args   = func_get_args();
      $format = array_shift($args);

      $this->output->writeln(vsprintf('<info>'.$format.'</info>', $args));
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  protected function logDebug()
  {
    if ($this->output->getVerbosity()>=OutputInterface::VERBOSITY_DEBUG)
    {
      $args   = func_get_args();
      $format = array_shift($args);

      $this->output->writeln(vsprintf('<info>'.$format.'</info>', $args));
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
