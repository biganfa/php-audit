<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for creating SQL statements for adding new columns to an audit table.
 */
class AlterAuditTableAddColumns
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
   * @param array[] $columns         The metadata of the new columns of the audit table (i.e. the audit columns and
   *                                 columns of the data table).
   */
  public function __construct($auditSchemaName, $tableName, $columns)
  {
    $this->auditSchemaName = $auditSchemaName;
    $this->tableName       = $tableName;
    $this->columns         = $columns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns a SQL statement for adding new columns to the audit table.
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
