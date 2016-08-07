<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Metadata;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for the metadata of a column.
 */
class ColumnMetadata
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The properties of table columns that are stored by this class.
   *
   * var string[]
   */
  private static $fields = ['column_name',
                            'column_type',
                            'is_nullable',
                            'character_set_name',
                            'collation_name'];

  /**
   * The the properties of this table column.
   *
   * @var array<string,string>
   */
  private $properties = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[] $properties The metadata of the column.
   */
  public function __construct($properties)
  {
    foreach (self::$fields as $field)
    {
      $this->properties[$field] = $properties[$field];
    }

    // XXX Must be in some other place, i guess.
    if ($this->properties['column_type']==='timestamp')
    {
      $this->properties['column_type'] = $this->properties['column_type'].' NULL';
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the properties of this table column as an array.
   *
   * @return array[]
   */
  public function getProperties()
  {
    return $this->properties;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a property of this table column.
   *
   * @param string $name The name of the property.
   *
   * @return string
   */
  public function getProperty($name)
  {
    return $this->properties[$name];
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
