<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySQl\Table;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for column type metadata.
 */
class ColumnType
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The metadata of the column.
   *
   * @var array[]
   */
  protected $columnType = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[]|Columns $columnProperties The metadata of the column.
   */
  public function __construct($columnProperties)
  {
    $this->columnType = $columnProperties;

    if (isset($this->columnType['column_type']))
    {
      if ($this->columnType['column_type']==='timestamp')
      {
        $this->columnType['column_type'] = $this->columnType['column_type'].' NULL';
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get columns type.
   *
   * @return \array[]
   */
  public function getType()
  {
    return $this->columnType;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get column property.
   *
   * @param string $propertyName The property name.
   *
   * @return string|null
   */
  public function getProperty($propertyName)
  {
    if (isset($this->columnType[$propertyName]))
    {
      return $this->columnType[$propertyName];
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
