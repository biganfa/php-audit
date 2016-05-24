<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;

//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit\ColumnTypes;
use SetBased\Audit\MySql\DataLayer;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Helper\TableSeparator;

/**
 * A helper class for creating printing Tables.
 */
class TableHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Array with rows for table.
   *
   * @var \array[]
   */
  private $rows = [];

  /**
   * Audit columns from config file.
   *
   * @var array
   */
  private $auditColumns;

  /**
   * Table options from audit schema.
   *
   * @var array
   */
  private $auditTableOptions;

  /**
   * Table options from data schema.
   *
   * @var array
   */
  private $dataTableOptions;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param string  $dataSchema      Data schema name.
   * @param string  $auditSchema     Audit schema name.
   * @param string  $tableName       The table name.
   * @param array[] $theAuditColumns Audit columns from config file.
   */
  public function __construct($dataSchema, $auditSchema, $tableName, $theAuditColumns)
  {
    $this->auditColumns      = $theAuditColumns;
    $this->auditTableOptions = DataLayer::getTableOptions($auditSchema, $tableName);
    $this->dataTableOptions  = DataLayer::getTableOptions($dataSchema, $tableName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Append row with table option.
   *
   * @param string $theOption
   */
  public function appendTableOption($theOption)
  {
    $tableRow               = ['column_name'        => $theOption,
                               'data_column_type'   => $this->dataTableOptions[$theOption],
                               'audit_column_type'  => $this->auditTableOptions[$theOption],
                               'config_column_type' => null];
    $this->rows[$theOption] = RowHelper::createTableRow($tableRow);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Appends rows.
   *
   * @param \array[] $theRows
   */
  public function appendRows($theRows)
  {
    /** @var ColumnTypes $row */
    foreach ($theRows as $row)
    {
      RowHelper::appendRow($this->rows, $row);
    }
    $this->rows[] = new TableSeparator();
    $this->appendTableOption('engine');
    $this->appendTableOption('character_set_name');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add highlighting to columns.
   */
  public function addHighlighting()
  {
    $styledColumns = [];
    foreach ($this->rows as $column)
    {
      $styledColumn = $column;
      if (is_array($column))
      {
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
          else if (strcmp($column['data_table_type'], $column['audit_table_type']))
          {
            $styledColumn['column_name']      = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
            $styledColumn['data_table_type']  = sprintf('<mm_type>%s</>', $styledColumn['data_table_type']);
            $styledColumn['audit_table_type'] = sprintf('<mm_type>%s</>', $styledColumn['audit_table_type']);
          }
        }
        else
        {
          // Highlighting for audit table column types and audit_columns in config file.
          $searchColumn = StaticDataLayer::searchInRowSet('column_name', $styledColumn['column_name'], $this->auditColumns);
          if (isset($searchColumn))
          {
            $configType = $this->auditColumns[$searchColumn]['column_type'];
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
      }
      $styledColumns[] = $styledColumn;
    }

    $this->rows = $styledColumns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get rows.
   *
   * @return \array[]
   */
  public function getRows()
  {
    return $this->rows;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
