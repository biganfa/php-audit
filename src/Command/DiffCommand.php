<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\Command;

use SetBased\Audit\Columns;
use SetBased\Audit\MySql\Command\AuditCommand;
use SetBased\Audit\MySql\DataLayer;
use SetBased\Stratum\MySql\StaticDataLayer;
use SetBased\Stratum\Style\StratumStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Command for comparing data tables with audit tables.
 */
class DiffCommand extends AuditCommand
{
  //--------------------------------------------------------------------------------------------------------------------
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
   * @var string
   */
  private $full;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check full full and return array without new or obsolete columns if full not set.
   *
   * @param array[] $columns The metadata of the columns of a table.
   *
   * @return array[]
   */
  private static function removeMatchingColumns($columns)
  {
    $cleaned = [];
    foreach ($columns as $column)
    {
      if (($column['data_table_type']!=$column['audit_table_type'] && $column['audit_table_type']!=$column['config_type']) || ($column['audit_table_type']!=$column['config_type'] && !empty($column['config_type'])))
      {
        $cleaned[] = $column;
      }
    }

    return $cleaned;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this->setName('diff')
         ->setDescription('Compares data tables and audit tables')
         ->addArgument('config file', InputArgument::OPTIONAL, 'The audit configuration file', 'etc/audit.json')
         ->addOption('full', 'f', InputOption::VALUE_NONE, 'Show all columns');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io = new StratumStyle($input, $output);

    // Style for column names with miss matched column types.
    $style = new OutputFormatterStyle(null, 'red');
    $output->getFormatter()->setStyle('mm_column', $style);

    // Style for column types of columns with miss matched column types.
    $style = new OutputFormatterStyle('yellow');
    $output->getFormatter()->setStyle('mm_type', $style);

    // Style for obsolete tables.
    $style = new OutputFormatterStyle('yellow');
    $output->getFormatter()->setStyle('obsolete_table', $style);

    // Style for missing tables.
    $style = new OutputFormatterStyle('red');
    $output->getFormatter()->setStyle('miss_table', $style);

    $this->configFileName = $input->getArgument('config file');
    $this->readConfigFile();

    $this->full = $input->getOption('full');

    $this->connect($this->config);

    $this->listOfTables();

    $this->getDiff();
    $this->printDiff($output);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add highlighting to columns.
   *
   * @param array[] $columns The metadata of the columns.
   *
   * @return array[]
   */
  private function addHighlighting($columns)
  {
    $styledColumns = [];
    foreach ($columns as $column)
    {
      $styledColumn = $column;
      // Highlighting for data table column types and audit.
      if (!empty($column['data_table_type']))
      {
        if (isset($column['data_table_type']) && !isset($column['audit_table_type']))
        {
          $styledColumn['column_name']     = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
          $styledColumn['data_table_type'] = sprintf('<mm_type>%s</>', $styledColumn['data_table_type']);
        }
        else if (!isset($column['data_table_type']) && isset($column['audit_table_type']))
        {
          $styledColumn['audit_table_type'] = sprintf('<mm_type>%s</>', $styledColumn['audit_table_type']);
        }
        else if (strcmp($column['data_table_type'], $column['audit_table_type']) && !isset($configType))
        {
          $styledColumn['column_name']      = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
          $styledColumn['data_table_type']  = sprintf('<mm_type>%s</>', $styledColumn['data_table_type']);
          $styledColumn['audit_table_type'] = sprintf('<mm_type>%s</>', $styledColumn['audit_table_type']);
        }
      }
      else
      {
        // Highlighting for audit table column types and audit_columns in config file.
        $searchColumn = StaticDataLayer::searchInRowSet('column_name', $styledColumn['column_name'], $this->config['audit_columns']);
        if (isset($searchColumn))
        {
          $configType = $this->config['audit_columns'][$searchColumn]['column_type'];
          if (isset($configType) && !isset($column['audit_table_type']))
          {
            $styledColumn['column_name'] = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
            $styledColumn['config_type'] = sprintf('<mm_type>%s</>', $styledColumn['config_type']);
          }
          else if (!isset($configType) && isset($column['audit_table_type']))
          {
            $styledColumn['audit_table_type'] = sprintf('<mm_type>%s</>', $column['audit_table_type']);
          }
          else if (strcmp($configType, $column['audit_table_type']))
          {
            $styledColumn['column_name']      = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
            $styledColumn['audit_table_type'] = sprintf('<mm_type>%s</>', $column['audit_table_type']);
            $styledColumn['config_type']      = sprintf('<mm_type>%s</>', $styledColumn['config_type']);
          }
        }
      }
      $styledColumns[] = $styledColumn;
    }

    return $styledColumns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get the difference between data and audit tables.
   *
   * @param Columns $dataColumns  The table columns from data schema.
   * @param Columns $auditColumns The table columns from audit schema.
   *
   * @return array[]
   */
  private function createDiffArray($dataColumns, $auditColumns)
  {

    $diff = [];

    foreach ($this->config['audit_columns'] as $column)
    {
      $diff[$column['column_name']] = ['column_name'      => $column['column_name'],
                                       'data_table_type'  => null,
                                       'audit_table_type' => null,
                                       'config_type'      => $column['column_type']];
    }

    foreach ($auditColumns->getColumns() as $column)
    {
      $config_type = isset($diff[$column['column_name']]) ? $diff[$column['column_name']]['config_type'] : null;

      $diff[$column['column_name']] = ['column_name'      => $column['column_name'],
                                       'data_table_type'  => null,
                                       'audit_table_type' => $column['column_type'],
                                       'config_type'      => $config_type];
    }

    foreach ($dataColumns->getColumns() as $column)
    {
      $config_type      = isset($diff[$column['column_name']]) ? $diff[$column['column_name']]['config_type'] : null;
      $audit_table_type = isset($diff[$column['column_name']]) ? $diff[$column['column_name']]['audit_table_type'] : null;

      $diff[$column['column_name']] = ['column_name'      => $column['column_name'],
                                       'data_table_type'  => $column['column_type'],
                                       'audit_table_type' => $audit_table_type,
                                       'config_type'      => $config_type];
    }

    return $diff;
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
          $dataColumns  = new Columns(DataLayer::getTableColumns($this->config['database']['data_schema'], $table['table_name']));
          $auditColumns = DataLayer::getTableColumns($this->config['database']['audit_schema'], $table['table_name']);
          $auditColumns = $this->addNotNull($auditColumns);
          $auditColumns = new Columns($auditColumns);

          $this->diffColumns[$table['table_name']] = $this->createDiffArray($dataColumns, $auditColumns);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add not null to audit columns if it not nullable.
   *
   * @param array $theColumns Audit columns.
   *
   * @return array
   */
  private function addNotNull($theColumns)
  {
    $modifiedColumns = [];
    foreach ($theColumns as $column)
    {
      $modifiedColumn = $column;
      $auditColumn    = StaticDataLayer::searchInRowSet('column_name', $modifiedColumn['column_name'], $this->config['audit_columns']);
      if (isset($auditColumn))
      {
        if ($modifiedColumn['is_nullable']==='NO')
        {
          $modifiedColumn['column_type'] = sprintf('%s not null', $modifiedColumn['column_type']);
        }
      }
      $modifiedColumns[] = $modifiedColumn;
    }

    return $modifiedColumns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Writes the difference between the audit tables and metadata tables to the output.
   *
   * @param OutputInterface $output The output.
   */
  private function diffTables($output)
  {
    foreach ($this->config['tables'] as $tableName => $table)
    {
      $res = StaticDataLayer::searchInRowSet('table_name', $tableName, $this->auditSchemaTables);
      if ($table['audit'] && !isset($res))
      {
        $output->writeln(sprintf('<miss_table>%s</>', $tableName));
      }
      else if (!$table['audit'] && isset($res))
      {
        $output->writeln(sprintf('<obsolete_table>%s</>', $tableName));
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Writes the difference between the audit and data tables to the output.
   *
   * @param OutputInterface $output The output.
   */
  private function printDiff($output)
  {
    $first = true;
    if (isset($this->diffColumns))
    {
      foreach ($this->diffColumns as $tableName => $columns)
      {
        // Remove matching columns unless the full option is used.
        if (!$this->full)
        {
          $columns = self::removeMatchingColumns($columns);
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
            $output->writeln('');
          }

          // Write table name.
          $output->writeln($tableName);

          // Write table with columns.
          $columns = $this->addHighlighting($columns);
          $table   = new Table($output);
          $table->setHeaders(['column', 'data table', 'audit table', 'config'])
                ->setRows($columns);
          $table->render();
        }
      }
    }
    $this->diffTables($output);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
