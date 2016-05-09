<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of (table) columns.
 */
class Columns
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the columns.
   *
   * @var array[]
   */
  private $columns = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[] $columns The metadata of the columns.
   */
  public function __construct($columns)
  {
    foreach ($columns as $column)
    {
      $this->columns[$column['column_name']] = [
        'column_name'      => $column['column_name'],
        'column_type'      => $column['column_type'],
        'char_set'         => isset($column['char_set']) ? $column['char_set'] : null,
        'collation'        => isset($column['collation']) ? $column['collation'] : null,
        'audit_expression' => isset($column['expression']) ? $column['expression'] : null,
        'audit_value_type' => isset($column['value_type']) ? $column['value_type'] : null
      ];
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Generate array with audit columns and columns from data table.
   *
   * @param Columns $auditColumnsMetadata   AuditApplication columns for adding to exist columns
   * @param Columns $currentColumnsMetadata Exist table columns
   *
   * @return Columns
   */
  public static function combine($auditColumnsMetadata, $currentColumnsMetadata)
  {
    $columns = [];

    foreach ($auditColumnsMetadata->columns as $column)
    {
      $columns[] = ['column_name' => $column['column_name'],
                    'column_type' => $column['column_type']];
    }

    foreach ($currentColumnsMetadata->columns as $column)
    {
      if ($column['column_type']!='timestamp')
      {
        $columns[] = ['column_name' => $column['column_name'],
                      'column_type' => $column['column_type']];
      }
      else
      {
        $columns[] = ['column_name' => $column['column_name'],
                      'column_type' => $column['column_type'].' NULL'];
      }
    }

    return new Columns($columns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares two Columns objects and returns an array with columns that are in the first columns object and in the
   * second Columns object but have different types.
   *
   * @param Columns $columns1 The first Columns object.
   * @param Columns $columns2 The second Columns object.
   *
   * @return array[]
   */
  public static function differentColumnTypes($columns1, $columns2)
  {
    $diff = [];
    foreach ($columns2->columns as $column2)
    {
      if (isset($columns1->columns[$column2['column_name']]))
      {
        $column1 = $columns1->columns[$column2['column_name']];
        if ($column2['column_type']!=$column1['column_type'])
        {
          $diff[] = $column1;
        }
      }
    }

    return $diff;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares two Columns objects and returns an array with columns that are in the first columns object but not in the
   * second Columns object.
   *
   * @param Columns $columns1 The first Columns object.
   * @param Columns $columns2 The second Columns object.
   *
   * @return array[]
   */
  public static function notInOtherSet($columns1, $columns2)
  {
    $diff = [];
    if (isset($columns1))
    {
      foreach ($columns1->columns as $column1)
      {
        if (!isset($columns2->columns[$column1['column_name']]))
        {
          $diff[] = ['column_name' => $column1['column_name'],
                     'column_type' => $column1['column_type']];
        }
      }
    }

    return $diff;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the underlying array with metadata of the columns.
   *
   * @return array[]
   */
  public function getColumns()
  {
    return $this->columns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Return column type with character set and collation.
   *
   * @param string $columnName The column name.
   *
   * @return null|string
   */
  public function getColumnTypeWithCharSetCollation($columnName)
  {
    $columns = array_keys($this->columns);
    $key     = array_search($columnName, $columns);

    if ($key!==false)
    {
      $column              = $this->columns[$columns[$key]];
      $column['char_set']  = isset($column['char_set']) ? ' '.$column['char_set'] : '';
      $column['collation'] = isset($column['collation']) ? ' '.$column['collation'] : '';

      return sprintf('%s%s%s', $column['column_type'], $column['char_set'], $column['collation']);
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
