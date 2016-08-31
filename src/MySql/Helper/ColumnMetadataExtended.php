<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;

//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit\MySql\Metadata\ColumnMetadata;

/**
 * Class for extended column type.
 */
class ColumnMetadataExtended
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the column.
   *
   * @var array[]
   */
  private $columnTypes = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[]|ColumnMetadata $columnTypes The metadata of the column.
   * @param null|string            $typePrefix  Prefix for column type name.
   */
  public function __construct($columnTypes, $typePrefix)
  {
    $this->extendColumnTypes($columnTypes, $typePrefix);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create array with all columns types.
   *
   * @param array[]|ColumnMetadata $properties The metadata of the column.
   * @param null|string            $typePrefix Prefix for column type name.
   */
  public function extendColumnTypes($properties, $typePrefix)
  {
    $columnTypes = $properties->getProperties();
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
  public function getExtendMetadata()
  {
    return $this->columnTypes;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
