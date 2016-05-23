<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of (table) column types.
 */
class ColumnTypes
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the columns.
   *
   * @var array[]
   */
  private $columnTypes = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[]     $columnTypes The metadata of the column.
   * @param null|string $typePrefix  Prefix for column type name.
   */
  public function __construct($columnTypes, $typePrefix = null)
  {
    $this->appendColumnTypes($columnTypes, $typePrefix);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Return array with all columns types.
   *
   * @param array[] $columnTypes The metadata of the column.
   * @param string  $typePrefix  Prefix for column type name.
   *
   * @return \array[]
   */
  public function appendColumnTypes($columnTypes, $typePrefix = null)
  {
    foreach ($columnTypes as $typeName => $typeValue)
    {
      if ($typeName=='column_name')
      {
        if (!isset($this->columnTypes['column_name']))
        {
          $this->columnTypes[$typeName] = $typeValue;
        }
      }
      else
      {
        $format = '%s_%s';
        if (isset($typePrefix))
        {
          $this->columnTypes[sprintf($format, $typePrefix, $typeName)] = $typeValue;
        }
        else
        {
          $this->columnTypes[$typeName] = $typeValue;
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get columns types.
   *
   * @return array[]
   */
  public function getTypes()
  {
    return $this->columnTypes;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
