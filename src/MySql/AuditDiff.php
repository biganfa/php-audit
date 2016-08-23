<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql;

use SetBased\Audit\MySql\Helper\DiffTableHelper;
use SetBased\Audit\MySql\Metadata\TableColumnsMetadata;
use SetBased\Stratum\MySql\StaticDataLayer;
use SetBased\Stratum\Style\StratumStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for executing auditing actions for tables.
 */
class AuditDiff
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata (additional) audit columns (as stored in the config file).
   *
   * @var TableColumnsMetadata
   */
  private $auditColumnsMetadata;

  /**
   * The names of all tables in audit schema.
   *
   * @var array
   */
  private $auditSchemaTables;

  /**
   * The content of the configuration file.
   *
   * @var array
   */
  private $config;

  /**
   * The names of all tables in data schema.
   *
   * @var array
   */
  private $dataSchemaTables;

  /**
   * Array with columns for each table.
   * array [
   *    table_name [
   *            column [
   *                    data table type,
   *                    audit table type
   *                    ],
   *                      ...
   *               ]
   *       ]
   *
   * @var array[]
   */
  private $diffColumns;

  /**
   * If set all tables and columns are shown.
   *
   * @var boolean
   */
  private $full;

  /**
   * The Input interface.
   *
   * @var InputInterface
   */
  private $input;

  /**
   * The Output decorator.
   *
   * @var StratumStyle
   */
  private $io;

  /**
   * The Output interface.
   *
   * @var OutputInterface
   */
  private $output;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[]         $config The content of the configuration file.
   * @param StratumStyle    $io     The Output decorator.
   * @param InputInterface  $input
   * @param OutputInterface $output
   */
  public function __construct(&$config, $io, $input, $output)
  {
    $this->io     = $io;
    $this->config = &$config;
    $this->input  = $input;
    $this->output = $output;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The main method: executes the auditing actions for tables.
   *
   * @return int The exit status.
   */
  public function main()
  {
    // Style for column names with miss matched column types.
    $style = new OutputFormatterStyle(null, 'red');
    $this->output->getFormatter()->setStyle('mm_column', $style);

    // Style for column types of columns with miss matched column types.
    $style = new OutputFormatterStyle('yellow');
    $this->output->getFormatter()->setStyle('mm_type', $style);

    // Style for obsolete tables.
    $style = new OutputFormatterStyle('yellow');
    $this->output->getFormatter()->setStyle('obsolete_table', $style);

    // Style for missing tables.
    $style = new OutputFormatterStyle('red');
    $this->output->getFormatter()->setStyle('miss_table', $style);

    $this->full = $this->input->getOption('full');

    $this->resolveCanonicalAuditColumns();

    $this->listOfTables();

    $this->getDiff();

    $this->printDiff();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Writes the difference between the audit tables and metadata tables to the output.
   */
  private function diffTables()
  {
    foreach ($this->config['tables'] as $tableName => $table)
    {
      $res = StaticDataLayer::searchInRowSet('table_name', $tableName, $this->auditSchemaTables);
      if ($table['audit'] && !isset($res))
      {
        $this->output->writeln(sprintf('<miss_table>%s</>', $tableName));
      }
      else if (!$table['audit'] && isset($res))
      {
        $this->output->writeln(sprintf('<obsolete_table>%s</>', $tableName));
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Computes the difference between data and audit tables.
   */
  private function getDiff()
  {
    foreach ($this->dataSchemaTables as $table)
    {
      if ($this->config['tables'][$table['table_name']]['audit'])
      {

        $res = StaticDataLayer::searchInRowSet('table_name', $table['table_name'], $this->auditSchemaTables);
        if (isset($res))
        {
          $this->diffColumns[$table['table_name']] = new AuditDiffTable($this->config['database']['data_schema'],
                                                                        $this->config['database']['audit_schema'],
                                                                        $table['table_name'],
                                                                        $this->config['audit_columns']);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting list of all tables from information_schema of database from config file.
   */
  private function listOfTables()
  {
    $this->dataSchemaTables = AuditDataLayer::getTablesNames($this->config['database']['data_schema']);

    $this->auditSchemaTables = AuditDataLayer::getTablesNames($this->config['database']['audit_schema']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Writes the difference between the audit and data tables to the output.
   */
  private function printDiff()
  {
    $first = true;
    if (isset($this->diffColumns))
    {
      /** @var AuditDiffTable $diffTable */
      foreach ($this->diffColumns as $tableName => $diffTable)
      {
        $columns = $diffTable->getDiffColumns();
        // Remove matching columns unless the full option is used.
        if (!$this->full)
        {
          $columns = $diffTable->removeMatchingColumns();
        }

        if (!empty($columns))
        {
          // Add an empty line between tables.
          if ($first)
          {
            $first = false;
          }
          else
          {
            $this->output->writeln('');
          }

          // Write table name.
          $this->output->writeln($tableName);

          // Write table with columns.
          $rows = new DiffTableHelper($this->config['database']['data_schema'],
                                      $this->config['database']['audit_schema'],
                                      $tableName,
                                      $this->config['audit_columns'],
                                      $this->full);
          $rows->appendRows($columns);
          $rows->addHighlighting();
          $table = new Table($this->output);
          $table->setHeaders(['column', 'data table', 'audit table', 'config'])
                ->setRows($rows->getRows());
          $table->render();
        }
      }
      $this->diffTables();
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Resolves the canonical column types of the audit table columns.
   */
  private function resolveCanonicalAuditColumns()
  {
    if (empty($this->config['audit_columns']))
    {
      $this->auditColumnsMetadata = new TableColumnsMetadata();
    }
    else
    {
      $schema    = $this->config['database']['audit_schema'];
      $tableName = '_TMP_'.uniqid();
      AuditDataLayer::createTemporaryTable($schema, $tableName, $this->config['audit_columns']);
      $columns = AuditDataLayer::getTableColumns($schema, $tableName);
      AuditDataLayer::dropTemporaryTable($schema, $tableName);

      foreach ($this->config['audit_columns'] as $audit_column)
      {
        $key = StaticDataLayer::searchInRowSet('column_name', $audit_column['column_name'], $columns);
        if (isset($audit_column['value_type']))
        {
          $columns[$key]['value_type'] = $audit_column['value_type'];
        }
        if (isset($audit_column['expression']))
        {
          $columns[$key]['expression'] = $audit_column['expression'];
        }
      }

      $this->auditColumnsMetadata = new TableColumnsMetadata($columns, 'AuditColumnMetadata');
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
