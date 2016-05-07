<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;

//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit\MySql\DataLayer;
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
   * @param string $dataSchema  Data schema name.
   * @param string $auditSchema Audit schema name.
   * @param string $tableName   The table name.
   */
  public function __construct($dataSchema, $auditSchema, $tableName)
  {
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
    $this->rows[$theOption] = ['column_name'      => $theOption,
                               'data_table_type'  => $this->dataTableOptions[$theOption],
                               'audit_table_type' => $this->auditTableOptions[$theOption],
                               'config_type'      => null];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Appends rows.
   *
   * @param \array[] $theRows
   */
  public function appendRows($theRows)
  {
    foreach ($theRows as $row)
    {
      $this->rows[] = $row;
    }
    $this->rows[] = new TableSeparator();
    $this->appendTableOption('engine');
    $this->appendTableOption('character_set_name');
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
