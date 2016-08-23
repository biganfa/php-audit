<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Command;

use SetBased\Audit\MySql\Audit;
use SetBased\Audit\MySql\AuditDataLayer;
use SetBased\Exception\RuntimeException;
use SetBased\Stratum\Style\StratumStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Command for creating audit tables and audit triggers.
 */
class AuditCommand extends MySqlBaseCommand
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Tables metadata from config file.
   *
   * @var array
   */
  protected $configMetadata;

  /**
   * File name for config metadata.
   *
   * @var array
   */
  protected $configMetadataFile;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this->setName('audit')
         ->setDescription('Create (missing) audit table and (re)creates audit triggers')
         ->addArgument('config file', InputArgument::OPTIONAL, 'The audit configuration file');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io = new StratumStyle($input, $output);

    $this->configFileName = $input->getArgument('config file');
    $this->readConfigFile();

    $this->readMetadata();

    // Create database connection with params from config file
    $this->connect($this->config);

    $audit  = new Audit($this->config, $this->configMetadata, $this->io);
    $status = $audit->main();

    // Drop database connection
    AuditDataLayer::disconnect();

    $this->rewriteConfig();

    return $status;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Read tables metadata from config file.
   */
  protected function readMetadata()
  {
    if (isset($this->config['metadata']))
    {
      $this->configMetadataFile = dirname($this->configFileName).'/'.$this->config['metadata'];
      $content                  = file_get_contents($this->configMetadataFile);

      $this->configMetadata = (array)json_decode($content, true);
      if (json_last_error()!=JSON_ERROR_NONE)
      {
        throw new RuntimeException("Error decoding JSON: '%s'.", json_last_error_msg());
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Rewrites the config file with updated data.
   */
  protected function rewriteConfig()
  {
    // Return immediately when the config file must not be rewritten.
    if (!$this->rewriteConfigFile) return;

    $this->writeTwoPhases($this->configFileName, json_encode($this->config, JSON_PRETTY_PRINT));
    $this->writeTwoPhases($this->configMetadataFile, json_encode($this->configMetadata, JSON_PRETTY_PRINT));
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
