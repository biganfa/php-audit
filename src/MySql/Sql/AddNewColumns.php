<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit\Columns;
use SetBased\Audit\MySql\DataLayer;

/**
 * Class for creating and executing SQL statements for creating audit tables.
 */
class AddNewColumns
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The name of the audit schema.
   *
   * @var string
   */
  private $auditSchemaName;

  /**
   * The array of new columns for adding to table.
   *
   * @var array[]
   */
  private $columns;

  /**
   * The name of the audit table.
   *
   * @var string
   */
  private $tableName;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param string  $auditSchemaName The name of the audit schema.
   * @param string  $tableName       The name of the table.
   * @param array[] $columns         The metadata of the columns of the audit table (i.e. the audit columns and columns
   *                                 of the data table).
   */
  public function __construct($auditSchemaName,
                              $tableName,
                              $columns)
  {
    $this->auditSchemaName = $auditSchemaName;
    $this->tableName       = $tableName;
    $this->columns         = $columns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a SQL statement for creating the audit table.
   *
   * @return string
   */
  public function buildStatement()
  {
    $sql = sprintf('alter table `%s`.`%s`', $this->auditSchemaName, $this->tableName);
    foreach ($this->columns as $column)
    {
      $sql .= ' add `'.$column['column_name'].'` '.$column['column_type'];
      if (isset($column['after']))
      {
        $sql .= ' after `'.$column['after'].'`';
      }
      else
      {
        $sql .= ' first';
      }
      if (end($this->columns)!==$column)
      {
        $sql .= ',';
      }
    }

    return $sql;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
