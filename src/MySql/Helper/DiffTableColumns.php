<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;

use SetBased\Audit\MySql\Metadata\ColumnMetadata;
use SetBased\Audit\MySql\Metadata\TableColumnsMetadata;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class container for all column types like audit,data and config.
 */
class DiffTableColumns
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
   * @param TableColumnsMetadata $configColumns The table columns from config file.
   * @param TableColumnsMetadata $auditColumns  The table columns from audit schema.
   * @param TableColumnsMetadata $dataColumns   The table columns from data schema.
   */
  public function __construct($configColumns, $auditColumns, $dataColumns)
  {
    $auditConfigTypes = $configColumns;
    $auditTypes       = $auditColumns;
    $dataTypes        = $dataColumns;
    $allTypes         = ['config' => $auditConfigTypes, 'audit' => $auditTypes, 'data' => $dataTypes];

    $this->appendColumnTypes($allTypes);
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
  /**
   * Add to array all columns types.
   *
   * @param array<string,object> $allTypes The metadata of the column.
   */
  private function appendColumnTypes($allTypes)
  {
    /** @var TableColumnsMetadata $typesArray */
    foreach ($allTypes as $typePrefix => $typesArray)
    {
      $typesArray = $typesArray->getColumns();
      /** @var ColumnMetadata $type */
      foreach ($typesArray as $type)
      {
        if (isset($this->columnTypes[$type->getProperty('column_name')]))
        {
          /** @var ColumnMetadataExtended */
          $this->columnTypes[$type->getProperty('column_name')]->extendColumnTypes($type, $typePrefix);
        }
        else
        {
          $this->columnTypes[$type->getProperty('column_name')] = new ColumnMetadataExtended($type, $typePrefix);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
