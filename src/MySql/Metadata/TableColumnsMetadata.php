<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Metadata;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of a set of table columns.
 */
class TableColumnsMetadata
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the columns.
   *
   * @var array<string,ColumnMetadata>
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
      $this->columns[$column['column_name']] = new ColumnMetadata($column);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Combines the metadata of two sets of metadata of table columns.
   *
   * @param TableColumnsMetadata $columns1 The first set of metadata of table columns.
   * @param TableColumnsMetadata $columns2 The second set of metadata of table columns.
   *
   * @return TableColumnsMetadata
   */
  public static function combine($columns1, $columns2)
  {
    $columns = [];

    foreach ($columns1->columns as $column)
    {
      $columns[] = $column;
    }

    foreach ($columns2->columns as $column)
    {
      $columns[] = $column;
    }

    return new TableColumnsMetadata($columns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares two sets of metadata of table columns and returns a set of metadata of table columns the are in both sets
   * but have different column type.
   *
   * @param TableColumnsMetadata $columns1 The first sets of metadata of table columns.
   * @param TableColumnsMetadata $columns2 The second sets of metadata of table columns.
   *
   * @return TableColumnsMetadata
   */
  public static function differentColumnTypes($columns1, $columns2)
  {
    $diff = [];
    foreach ($columns1->columns as $column_name => $column1)
    {
      if (isset($columns2->columns[$column_name]))
      {
        if ($columns2[$column_name]['column_type']!=$column1['column_type'])
        {
          $diff[] = $column1;
        }
      }
    }

    return new TableColumnsMetadata($diff);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares two sets of metadata of table columns and returns a set of metadata of table columns that are in the first
   * sets of metadata of table columns but not in the second sets of metadata of table columns.
   *
   * @param TableColumnsMetadata $columns1 The first sets of metadata of table columns.
   * @param TableColumnsMetadata $columns2 The second sets of metadata of table columns.
   *
   * @return TableColumnsMetadata
   */
  public static function notInOtherSet($columns1, $columns2)
  {
    $diff = [];
    foreach ($columns1->columns as $column_name => $column1)
    {
      if (!isset($columns2->columns[$column_name]))
      {
        $diff[] = $column1;
      }
    }

    return new TableColumnsMetadata($diff);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the underlying array with metadata of the columns.
   *
   * @return array<string,ColumnMetadata>
   */
  public function getColumns()
  {
    return $this->columns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the number of columns.
   *
   * @return int
   */
  public function getNumberOfColumns()
  {
    return count($this->columns);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns previous column of a columns. Returns null if the column name is not found in this TableColumnsMetadata.
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
