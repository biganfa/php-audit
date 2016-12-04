<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;

use SetBased\Audit\MySql\AuditDataLayer;
use SetBased\Audit\MySql\Metadata\MultiSourceColumnMetadata;
use SetBased\Audit\MySql\Metadata\TableColumnsMetadata;
use SetBased\Stratum\MySql\StaticDataLayer;
use Symfony\Component\Console\Helper\TableSeparator;

//----------------------------------------------------------------------------------------------------------------------
/**
 * A helper class for creating printing Tables.
 */
class DiffTableHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Audit columns from config file.
   *
   * @var array
   */
  private $auditColumns;

  /**
   * Audit options from audit schema.
   *
   * @var array
   */
  private $auditTableOptions;

  /**
   * Audit options from data schema.
   *
   * @var array
   */
  private $dataTableOptions;

  /**
   * Check existing separator.
   *
   * @var bool
   */
  private $existSeparator = false;

  /**
   * Full option.
   *
   * @var bool
   */
  private $fullOption;

  /**
   * Array with rows for table.
   *
   * @var \array[]
   */
  private $rows = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param string  $dataSchema      Data schema name.
   * @param string  $auditSchema     Audit schema name.
   * @param string  $tableName       The table name.
   * @param array[] $theAuditColumns Audit columns from config file.
   * @param bool    $fullOption      If set append table options to rows.
   */
  public function __construct($dataSchema, $auditSchema, $tableName, $theAuditColumns, $fullOption)
  {
    $this->auditColumns      = $theAuditColumns;
    $this->fullOption        = $fullOption;
    $this->auditTableOptions = AuditDataLayer::getTableOptions($auditSchema, $tableName);
    $this->dataTableOptions  = AuditDataLayer::getTableOptions($dataSchema, $tableName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add highlighting to columns.
   */
  public function addHighlighting()
  {
    $styledColumns = [];
    foreach ($this->rows as $key => $column)
    {
      $styledColumn = $column;
      if (is_array($column))
      {
        // Highlighting for data table column types and audit.
        if (!empty($column['data']))
        {
          if (isset($column['data']) && !isset($column['audit']))
          {
            if (!isset($column['column_name']))
            {
              $styledColumns[$key - 1]['column_name'] = sprintf('<mm_column>%s</>', $styledColumns[$key - 1]['column_name']);
            }
            $styledColumn['column_name'] = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
            $styledColumn['data']        = sprintf('<mm_type>%s</>', $styledColumn['data']);
          }
          else if (!isset($column['data']) && isset($column['audit']))
          {
            $styledColumn['audit'] = sprintf('<mm_type>%s</>', $styledColumn['audit']);
          }
          else if (strcmp($column['data'], $column['audit']))
          {
            if (!isset($column['column_name']))
            {
              $styledColumns[$key - 1]['column_name'] = sprintf('<mm_column>%s</>', $styledColumns[$key - 1]['column_name']);
            }
            $styledColumn['column_name'] = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
            $styledColumn['data']        = sprintf('<mm_type>%s</>', $styledColumn['data']);
            $styledColumn['audit']       = sprintf('<mm_type>%s</>', $styledColumn['audit']);
          }
        }
        else
        {
          // Highlighting for audit table column types and audit_columns in config file.
          $searchColumn = StaticDataLayer::searchInRowSet('column_name', $styledColumn['column_name'], $this->auditColumns);
          if (isset($searchColumn))
          {
            $configType = $this->auditColumns[$searchColumn]['column_type'];
            if (isset($configType) && !isset($column['audit']))
            {
              $styledColumn['column_name'] = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
              $styledColumn['config']      = sprintf('<mm_type>%s</>', $styledColumn['config']);
            }
            else if (!isset($configType) && isset($column['audit']))
            {
              $styledColumn['audit'] = sprintf('<mm_type>%s</>', $column['audit']);
            }
            else if (strcmp($configType, $column['audit']))
            {
              $styledColumn['column_name'] = sprintf('<mm_column>%s</>', $styledColumn['column_name']);
              $styledColumn['audit']       = sprintf('<mm_type>%s</>', $column['audit']);
              $styledColumn['config']      = sprintf('<mm_type>%s</>', $styledColumn['config']);
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
   * Appends rows.
   *
   * @param TableColumnsMetadata $theRows Rows array.
   */
  public function appendRows($theRows)
  {
    /** @var MultiSourceColumnMetadata $rowMetadata */
    foreach ($theRows->getColumns() as $columnName => $rowMetadata)
    {
      DiffTableRowHelper::appendRow($this->rows, $rowMetadata, $columnName);
    }
    $this->appendTableOption('engine');
    $this->appendTableOption('character_set_name', 'character set');
    $this->appendTableOption('table_collation', 'collation');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Append row with table option.
   *
   * @param string      $theOption The option.
   * @param null|string $theName   Display name.
   */
  public function appendTableOption($theOption, $theName = null)
  {
    if ($this->dataTableOptions[$theOption]!=$this->auditTableOptions[$theOption] || $this->fullOption)
    {
      if (!$this->existSeparator)
      {
        $this->rows[]         = new TableSeparator();
        $this->existSeparator = true;
      }
      if ($theName===null)
      {
        $theName = $theOption;
      }
      $tableRow               = ['column_name' => $theName,
                                 'data'        => $this->dataTableOptions[$theOption],
                                 'audit'       => $this->auditTableOptions[$theOption],
                                 'config'      => null];
      $this->rows[$theOption] = $tableRow;
    }
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