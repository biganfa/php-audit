<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

use SetBased\Audit\MySql\Helper\CompoundSyntaxStore;
use SetBased\Audit\MySql\Table\Columns;

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
   * @var Columns
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
   * @param Columns $columns         The metadata of the new columns of the audit table (i.e. the audit columns and
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
    $code = new CompoundSyntaxStore();

    $code->append(sprintf('alter table `%s`.`%s`', $this->auditSchemaName, $this->tableName));
    foreach ($this->columns->getColumns() as $column)
    {
      $code->append(sprintf('  add `%s` %s', $column['column_name'], $column['column_type']), false);
      if (isset($column['after']))
      {
        $code->appendToLastLine(sprintf(' after `%s`', $column['after']));
      }
      else
      {
        $code->appendToLastLine(' first');
      }
      $columns = $this->columns->getColumns();
      if (end($columns)!==$column)
      {
        $code->appendToLastLine(',');
      }
    }

    return $code->getCode();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
