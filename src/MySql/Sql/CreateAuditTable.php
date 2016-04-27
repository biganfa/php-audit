<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit\Columns;

/**
 * Class for creating and executing SQL statements for creating audit tables.
 */
class CreateAuditTable
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The name of the audit schema.
   *
   * @var string
   */
  private $auditSchemaName;

  /**
   * The name of the table.
   *
   * @var Columns
   */
  private $columns;

  /**
   * The metadata of the columns of the audit table (i.e. the audit columns and columns of the data table).
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
   * @param Columns $columns         The metadata of the columns of the audit table (i.e. the audit columns and columns
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
    $sql = sprintf('create table `%s`.`%s`(', $this->auditSchemaName, $this->tableName);
    foreach ($this->columns->getColumns() as $column)
    {
      $sql .= sprintf('`%s` %s', $column['column_name'], $column['column_type']);
      if (end($this->columns->getColumns())!==$column)
      {
        $sql .= ',';
      }
    }
    $sql .= ')';

    return $sql;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
