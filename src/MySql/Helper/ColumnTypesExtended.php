<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;

//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit\MySql\Table\Columns;
use SetBased\Audit\MySQl\Table\ColumnType;

/**
 * A helper class for column types.
 */
class ColumnTypesExtended
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Contains all column types from audit and data schemas.
   *
   * @var array[]
   */
  private $columnTypes = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor
   *
   * @param array[] $configColumns The table columns from config file.
   * @param Columns $auditColumns  The table columns from audit schema.
   * @param Columns $dataColumns   The table columns from data schema.
   */
  public function __construct($configColumns, $auditColumns, $dataColumns)
  {
    $auditConfigTypes = new Columns($configColumns);
    $auditTypes       = $auditColumns;
    $dataTypes        = $dataColumns;
    $allTypes         = ['config' => $auditConfigTypes, 'audit' => $auditTypes, 'data' => $dataTypes];

    $this->appendColumnTypes($allTypes);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add to array all columns types.
   *
   * @param array[] $columnTypes The metadata of the column.
   */
  private function appendColumnTypes($columnTypes)
  {
    /** @var Columns $typesArray */
    foreach ($columnTypes as $typePrefix => $typesArray)
    {
      $typesArray = $typesArray->getColumns();
      /** @var ColumnType $type */
      foreach ($typesArray as $type)
      {
        if (isset($this->columnTypes[$type->getProperty('column_name')]))
        {
          $this->columnTypes[$type->getProperty('column_name')]->extendColumnTypes($type, $typePrefix);
        }
        else
        {
          $this->columnTypes[$type->getProperty('column_name')] = new ColumnTypesHelper($type, $typePrefix);
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
