<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Table;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of table columns.
 */
class Columns
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the columns.
   *
   * @var array<string,ColumnType>
   */
  private $columns = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[] $columns The metadata of the columns as returned by DataLayer::getTableColumns().
   */
  public function __construct($columns)
  {
    foreach ($columns as $column)
    {
      if (!is_array($column))
      {
        /** @var ColumnType $column */
        $column = $column->getType();
      }
      /** @var array $column */
      $this->columns[$column['column_name']] = new ColumnType($column);
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
      $columns[] = $column;
    }

    foreach ($currentColumnsMetadata->columns as $column)
    {
      $columns[] = $column;
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
   * @return Columns
   */
  public static function differentColumnTypes($columns1, $columns2)
  {
    $diff = [];
    foreach ($columns2->columns as $column2)
    {
      if (!is_array($column2))
      {
        /** @var ColumnType $column2 */
        $column2 = $column2->getType();
      }
      if (isset($columns1->columns[$column2['column_name']]))
      {
        $column1 = $columns1->columns[$column2['column_name']];
        if (!is_array($column1))
        {
          /** @var ColumnType $column1 */
          $column1 = $column1->getType();
        }
        if ($column2['column_type']!=$column1['column_type'])
        {
          $diff[] = $column1;
        }
      }
    }

    return new Columns($diff);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares two Columns objects and returns an array with columns that are in the first columns object but not in the
   * second Columns object.
   *
   * @param Columns $columns1 The first Columns object.
   * @param Columns $columns2 The second Columns object.
   *
   * @return Columns
   */
  public static function notInOtherSet($columns1, $columns2)
  {
    $diff = [];
    if (isset($columns1))
    {
      foreach ($columns1->columns as $column1)
      {
        if (!is_array($column1))
        {
          /** @var ColumnType $column1 */
          $column1 = $column1->getType();
        }
        if (!isset($columns2->columns[$column1['column_name']]))
        {
          $diff[] = $column1;
        }
      }
    }

    return new Columns($diff);
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
      $column                       = $this->columns[$columns[$key]];
      $column['character_set_name'] = isset($column['character_set_name']) ? ' '.$column['character_set_name'] : '';
      $column['collation_name']     = isset($column['collation_name']) ? ' '.$column['collation_name'] : '';

      return sprintf('%s%s%s', $column['column_type'], $column['character_set_name'], $column['collation_name']);
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns previous column of a columns. Returns null if the column name is not found in this Columns.
   *
   * @param string $columnName The column name.
   *
   * @return null|string
   */
  public function getPreviousColumn($columnName)
  {
    $columns = array_keys($this->columns);
    $key     = array_search($columnName, $columns);

    if ($key>=1)
    {
      return $columns[$key - 1];
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
