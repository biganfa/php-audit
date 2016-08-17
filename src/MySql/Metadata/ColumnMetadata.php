<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Metadata;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Metadata of table columns.
 */
class ColumnMetadata
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The properties of the column that are stored by this class.
   *
   * var string[]
   */
  protected static $fields = ['column_name',
                              'column_type',
                              'is_nullable',
                              'character_set_name',
                              'collation_name'];

  /**
   * The the properties of this table column.
   *
   * @var array<string,string>
   */
  protected $properties = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param array[] $properties The metadata of the column.
   */
  public function __construct($properties)
  {
    foreach (static::$fields as $field)
    {
      if (isset($properties[$field]))
      {
        $this->properties[$field] = $properties[$field];
      }
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
   * @return string|null
   */
  public function getProperty($name)
  {
    if (isset($this->properties[$name]))
    {
      return $this->properties[$name];
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Make this column nullable.
   */
  public function makeNullable()
  {
    $this->properties['is_nullable'] = 'YES';
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
