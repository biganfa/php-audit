<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Command for comparing data tables with audit tables.
 */
class CompareCommand extends BaseCommand
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this->setName('compare')
         ->setDescription('Compares data tables and audit tables')
         ->addOption('full', 'f', InputArgument::OPTIONAL, 'Show all columns');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {

  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
