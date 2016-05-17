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
   * @param string      $theOption The option.
   * @param null|string $theName   Display name.
   */
  public function appendTableOption($theOption, $theName = null)
  {
    if ($theName===null)
    {
      $theName = $theOption;
    }
    $this->rows[$theOption] = ['column_name'      => $theName,
                               'data_table_type'  => $this->dataTableOptions[$theOption],
                               'audit_table_type' => $this->auditTableOptions[$theOption],
                               'config_type'      => null];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Appends rows.
   *
   * @param \array[] $theRows       Rows array.
   * @param bool     $theOptionFlag If set append table options to rows.
   */
  public function appendRows($theRows, $theOptionFlag)
  {
    foreach ($theRows as $row)
    {
      $this->rows[] = $row;
    }
    if ($theOptionFlag)
    {
      $this->rows[] = new TableSeparator();
      $this->appendTableOption('engine');
      $this->appendTableOption('character_set_name', 'character set');
      $this->appendTableOption('table_collation', 'collation');
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
