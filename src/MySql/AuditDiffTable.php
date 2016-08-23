<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql;

use SetBased\Audit\MySql\Helper\DiffTableColumns;
use SetBased\Audit\MySql\Metadata\TableColumnsMetadata;
use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for executing auditing actions for tables.
 */
class AuditDiffTable
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Audit columns from config file
   *
   * @var array[]
   */
  private $auditColumns;

  /**
   * Audit database schema.
   *
   * @var string
   */
  private $auditSchema;

  /**
   * Data database schema.
   *
   * @var string
   */
  private $dataSchema;

  /**
   * Difference between data and audit tables.
   *
   * @var DiffTableColumns
   */
  private $diffColumns;

  /**
   * Table name.
   *
   * @var string
   */
  private $tableName;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param string  $dataSchema   Data database schema.
   * @param string  $auditSchema  Audit database schema.
   * @param string  $tableName    Table name.
   * @param array[] $auditColumns Audit columns from config file.
   */
  public function __construct($dataSchema, $auditSchema, $tableName, $auditColumns)
  {
    $this->dataSchema   = $dataSchema;
    $this->auditSchema  = $auditSchema;
    $this->tableName    = $tableName;
    $this->auditColumns = $auditColumns;

    $dataColumns  = new TableColumnsMetadata(AuditDataLayer::getTableColumns($this->dataSchema, $this->tableName));
    $auditColumns = AuditDataLayer::getTableColumns($this->auditSchema, $this->tableName);
    $auditColumns = $this->addNotNull($auditColumns);
    $auditColumns = new TableColumnsMetadata($auditColumns);

    $this->createDiffArray($dataColumns, $auditColumns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Return diff columns.
   *
   * @return array[]
   */
  public function getDiffColumns()
  {
    return $this->diffColumns->getTypes();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check full and return array without new or obsolete columns if full not set.   *
   *
   * @return array[]
   */
  public function removeMatchingColumns()
  {
    $cleaned = [];
    /** @var DiffTableColumns $column */
    foreach ($this->diffColumns as $column)
    {
      $columnsArray = $column->getTypes();
      if (!isset($columnsArray['data_column_type']))
      {
        if ($columnsArray['audit_column_type']!=$columnsArray['config_column_type'])
        {
          $cleaned[] = $column;
        }
      }
      elseif (!isset($columnsArray['config_column_type']))
      {
        if (($columnsArray['audit_column_type']!=$columnsArray['data_column_type']) || ($columnsArray['audit_character_set_name']!=$columnsArray['data_character_set_name'] || $columnsArray['audit_collation_name']!=$columnsArray['data_collation_name']))
        {
          $cleaned[] = $column;
        }
      }
      else
      {
        if (($columnsArray['data_column_type']!=$columnsArray['audit_column_type'] && $columnsArray['audit_column_type']!=$columnsArray['config_column_type']) || ($columnsArray['audit_column_type']!=$columnsArray['config_column_type'] && !empty($columnsArray['config_column_type'])))
        {
          $cleaned[] = $column;
        }
      }
    }

    return $cleaned;
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
      $auditColumn    = StaticDataLayer::searchInRowSet('column_name', $modifiedColumn['column_name'], $this->auditColumns);
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
   * Get the difference between data and audit tables.
   *
   * @param TableColumnsMetadata $dataColumns  The table columns from data schema.
   * @param TableColumnsMetadata $auditColumns The table columns from audit schema.
   */
  private function createDiffArray($dataColumns, $auditColumns)
  {
    $configColumns = new TableColumnsMetadata($this->auditColumns);
    $diff          = new DiffTableColumns($configColumns, $auditColumns, $dataColumns);

    $this->diffColumns = $diff;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
