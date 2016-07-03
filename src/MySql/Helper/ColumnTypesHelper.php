<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;

use SetBased\Audit\MySQl\Table\ColumnType;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for metadata of (table) column types.
 */
class ColumnTypesHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the column.
   *
   * @var array[]
   */
  protected $columnTypes = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[]|ColumnType $columnTypes The metadata of the column.
   * @param null|string        $typePrefix  Prefix for column type name.
   */
  public function __construct($columnTypes, $typePrefix)
  {
    $this->extendColumnTypes($columnTypes, $typePrefix);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create array with all columns types.
   *
   * @param array[]|ColumnType $columnTypes The metadata of the column.
   * @param null|string        $typePrefix  Prefix for column type name.
   */
  public function extendColumnTypes($columnTypes, $typePrefix)
  {
    $columnTypes = $columnTypes->getType();
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
   * Get columns type.
   *
   * @return \array[]
   */
  public function getTypes()
  {
    return $this->columnTypes;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
