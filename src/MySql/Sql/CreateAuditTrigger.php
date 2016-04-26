<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

use SetBased\Audit\Columns;
use SetBased\Exception\FallenException;
use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for creating SQL statements for creating audit triggers.
 */
class CreateAuditTrigger
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Additional SQL statements.
   *
   * @var string[]
   */
  private $additionalSql;

  /**
   * AuditApplication columns from metadata.
   *
   * @var Columns
   */
  private $auditColumns;

  /**
   * The name of the audit schema.
   *
   * @var string
   */
  private $auditSchemaName;

  /**
   * The name of the data schema.
   *
   * @var string
   */
  private $dataSchemaName;

  /**
   * The skip variable.
   *
   * @var string
   */
  private $skipVariable;

  /**
   * Table columns from metadata.
   *
   * @var Columns
   */
  private $tableColumns;

  /**
   * The name of the data table.
   *
   * @var string
   */
  private $tableName;

  /**
   * The trigger action (.e. INSERT, UPDATE, or DELETE).
   *
   * @var string
   */
  private $triggerAction;

  /**
   * The name of the trigger.
   *
   * @var string
   */
  private $triggerName;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a trigger on a table.
   *
   * @param string   $dataSchemaName  The name of the data schema.
   * @param string   $auditSchemaName The name of the audit schema.
   * @param string   $tableName       The name of the table.
   * @param string   $triggerAction   The trigger action (i.e. INSERT, UPDATE, or DELETE).
   * @param string   $triggerName     The name of the trigger.
   * @param Columns  $tableColumns    The data table columns.
   * @param Columns  $auditColumns    The audit table columns.
   * @param string   $skipVariable    The skip variable.
   * @param string[] $additionalSql   Additional SQL statements.
   */
  public function __construct($dataSchemaName,
                              $auditSchemaName,
                              $tableName,
                              $triggerName,
                              $triggerAction,
                              $auditColumns,
                              $tableColumns,
                              $skipVariable,
                              $additionalSql)
  {
    $this->dataSchemaName  = $dataSchemaName;
    $this->auditSchemaName = $auditSchemaName;
    $this->tableName       = $tableName;
    $this->triggerName     = $triggerName;
    $this->triggerAction   = $triggerAction;
    $this->skipVariable    = $skipVariable;
    $this->tableColumns    = $tableColumns;
    $this->auditColumns    = $auditColumns;
    $this->additionalSql   = $additionalSql;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the if clause for skipping a trigger.
   *
   * @param  string $theSkipVariable The skip variable (including @).
   *
   * @return string
   */
  private static function skipStatement($theSkipVariable)
  {
    $statement = '';
    if (isset($theSkipVariable))
    {
      $statement = sprintf("if (%s is null) then\n", $theSkipVariable);
    }

    return $statement;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the SQL code for creating an audit trigger.
   *
   * @throws FallenException
   */
  public function buildStatement()
  {
    $rowState = [];
    switch ($this->triggerAction)
    {
      case 'INSERT':
        $rowState[] = 'NEW';
        break;

      case 'DELETE':
        $rowState[] = 'OLD';
        break;

      case 'UPDATE':
        $rowState[] = 'OLD';
        $rowState[] = 'NEW';
        break;

      default:
        throw new FallenException('action', $this->triggerAction);
    }

    $sql = sprintf('
create trigger `%s`.`%s`
after %s on `%s`.`%s`
for each row
begin
',
                   $this->dataSchemaName,
                   $this->triggerName,
                   $this->triggerAction,
                   $this->dataSchemaName,
                   $this->tableName);

    $sql .= $this->skipStatement($this->skipVariable);

    if (is_array($this->additionalSql))
    {
      foreach ($this->additionalSql as $line)
      {
        $sql .= $line;
        $sql .= "\n";
      }
    }

    $sql .= $this->createInsertStatement($rowState[0]);
    if (sizeof($rowState)==2)
    {
      $sql .= $this->createInsertStatement($rowState[1]);
    }

    $sql .= isset($this->skipVariable) ? "end if;\n" : '';
    $sql .= 'end';

    return $sql;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an insert SQL statement for an audit table.
   *
   * @param string $rowState The row state (i.e. OLD or NEW).
   *
   * @return string
   */
  private function createInsertStatement($rowState)
  {
    $columnNames = '';
    foreach ($this->auditColumns->getColumns() as $column)
    {
      if ($columnNames) $columnNames .= ',';
      $columnNames .= sprintf('`%s`',$column['column_name']);
    }
    foreach ($this->tableColumns->getColumns() as $column)
    {
      if ($columnNames) $columnNames .= ',';
      $columnNames .= sprintf('`%s`',$column['column_name']);
    }

    $values = '';
    foreach ($this->auditColumns->getColumns() as $column)
    {
      if ($values) $values .= ',';
      if (isset($column['audit_value_type']))
      {
        switch ($column['audit_value_type'])
        {
          case 'ACTION':
            $values .= StaticDataLayer::quoteString($this->triggerAction);
            break;

          case 'STATE':
            $values .= StaticDataLayer::quoteString($rowState);
            break;

          default:
            throw new FallenException('audit_value_type', ($column['audit_value_type']));
        }
      }
      else
      {
        $values .= $column['audit_expression'];
      }
    }
    foreach ($this->tableColumns->getColumns() as $column)
    {
      if ($values) $values .= ',';
      $values .= sprintf('%s.`%s`', $rowState, $column['column_name']);
    }

    $insertStatement = sprintf('insert into `%s`.`%s`(%s)
values(%s);
',
                               $this->auditSchemaName,
                               $this->tableName,
                               $columnNames,
                               $values);

    return $insertStatement;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
