<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

use SetBased\Audit\MySql\DataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of columns.
 */
class Columns
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the columns.
   *
   * @var array[]
   */
  private $myColumns = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[] $theColumns The metadata of the columns.
   */
  public function __construct($theColumns)
  {
    foreach ($theColumns as $column)
    {
      $this->myColumns[$column['column_name']] = [
        'column_name'      => $column['column_name'],
        'column_type'      => $column['column_type'],
        'audit_expression' => isset($column['expression']) ? $column['expression'] : null,
        'audit_value_type' => isset($column['value_type']) ? $column['value_type'] : null
      ];
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Gets columns array.
   *
   * @return array[]
   */
  public function getColumns()
  {
    return $this->myColumns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compare two arrays for search columns from first array not set in second array
   *
   * @param array[] $theCurrentColumns   Search this columns
   * @param array[] $theColumnsForSearch Search in this array
   *
   * @return array[]
   */
  public static function notInOtherSet($theCurrentColumns, $theColumnsForSearch)
  {
    $not_in_other_columns = [];
    if (isset($theCurrentColumns))
    {
      foreach ($theCurrentColumns as $column)
      {
        $exist_column = DataLayer::searchInRowSet('column_name', $column['column_name'], $theColumnsForSearch);
        if (!isset($exist_column))
        {
          $not_in_other_columns[] = ['column_name' => $column['column_name'],
                                     'column_type' => $column['column_type']];
        }
      }
    }

    return $not_in_other_columns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Generate array with audit columns and columns from data table.
   *
   * @param Columns $theAuditColumnsMetadata   Audit columns for adding to exist columns
   * @param Columns $theCurrentColumnsMetadata Exist table columns
   *
   * @return Columns
   */
  public static function combine($theAuditColumnsMetadata, $theCurrentColumnsMetadata)
  {
    $columns = [];
    foreach ($theAuditColumnsMetadata->getColumns() as $column)
    {
      $columns[] = ['column_name' => $column['column_name'], 'column_type' => $column['column_type']];
    }
    foreach ($theCurrentColumnsMetadata->getColumns() as $column)
    {
      if ($column['column_type']!='timestamp')
      {
        $columns[] = ['column_name' => $column['column_name'], 'column_type' => $column['column_type'].' DEFAULT NULL'];
      }
      else
      {
        $columns[] = ['column_name' => $column['column_name'], 'column_type' => $column['column_type'].' NULL'];
      }
    }

    return new Columns($columns);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
