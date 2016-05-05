<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

use SetBased\Audit\Columns;
use SetBased\Exception\FallenException;
use SetBased\Exception\RuntimeException;
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
   * Indent level.
   *
   * @var int
   */
  private $indentLevel;

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

    $sql   = [];
    $sql[] = sprintf('create trigger `%s`.`%s`', $this->dataSchemaName, $this->triggerName);
    $sql[] = sprintf('after %s on `%s`.`%s`', strtolower($this->triggerAction), $this->dataSchemaName, $this->tableName);
    $sql[] = 'for each row';
    $sql[] = 'begin';

    if ($this->skipVariable!==null) $sql[] = sprintf('if (%s is null) then', $this->skipVariable);

    if (is_array($this->additionalSql))
    {
      foreach ($this->additionalSql as $line)
      {
        $sql[] = $line;
      }
    }

    $this->createInsertStatement($sql, $rowState[0]);
    if (sizeof($rowState)==2)
    {
      $this->createInsertStatement($sql, $rowState[1]);
    }

    if ($this->skipVariable!==null) $sql[] = 'end if;';
    $sql[] = 'end';

    return $this->writeIndent($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Adds an insert SQL statement to SQL code for a trigger.
   *
   * @param string[] $sql      The SQL code.
   * @param string   $rowState The row state (i.e. OLD or NEW).
   */
  private function createInsertStatement(&$sql, $rowState)
  {
    $this->createInsertStatementInto($sql);
    $this->createInsertStatementValues($sql, $rowState);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Adds the "insert into" part of an insert SQL statement to SQL code for a trigger.
   *
   * @param string[] $sql      The SQL code.
   */
  private function createInsertStatementInto(&$sql)
  {
    $columnNames = '';

    // First the audit columns.
    foreach ($this->auditColumns->getColumns() as $column)
    {
      if ($columnNames) $columnNames .= ',';
      $columnNames .= sprintf('`%s`', $column['column_name']);
    }

    // Second the audit columns.
    foreach ($this->tableColumns->getColumns() as $column)
    {
      if ($columnNames) $columnNames .= ',';
      $columnNames .= sprintf('`%s`', $column['column_name']);
    }

    $sql[] = sprintf('insert into `%s`.`%s`(%s)', $this->auditSchemaName, $this->tableName, $columnNames);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Adds the "values" part of an insert SQL statement to SQL code for a trigger.
   *
   * @param string[] $sql      The SQL code.
   * @param string   $rowState The row state (i.e. OLD or NEW).
   */
  private function createInsertStatementValues(&$sql, $rowState)
  {
    $values = '';

    // First the values for the audit columns.
    foreach ($this->auditColumns->getColumns() as $column)
    {
      if ($values) $values .= ',';
      switch (true)
      {
        case (isset($column['audit_value_type'])):
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
          break;

        case (isset($column['audit_expression'])):
          $values .= $column['audit_expression'];
          break;

        default:
          throw new RuntimeException('None of audit_value_type and audit_expression are set.');
      }
    }

    // Second the values for the audit columns.
    foreach ($this->tableColumns->getColumns() as $column)
    {
      if ($values) $values .= ',';
      $values .= sprintf('%s.`%s`', $rowState, $column['column_name']);
    }

    $sql[] = sprintf('values(%s);', $values);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the SQL statement with indents.
   *
   * @param string[] $sqlStatement The SQL query.
   *
   * @return string
   */
  private  function writeIndent($sqlStatement)
  {
    $sqlStatementWithIndent = [];
    foreach ($sqlStatement as $key => $line)
    {
      $line  = trim($line);
      $words = explode(' ', $line);
      if (count($words)>0)
      {
        switch ($words[0])
        {
          case 'begin':
            $this->indentLevel += 1;
            break;

          case 'if':
            $line = str_repeat('  ', $this->indentLevel).$line;
            $this->indentLevel += 1;
            break;

          case 'end':
            if ($this->indentLevel>0)
            {
              $this->indentLevel -= 1;
            }
            $line = str_repeat('  ', $this->indentLevel).$line;
            break;

          default:
            $line = str_repeat('  ', $this->indentLevel).$line;
            break;
        }
      }
      $sqlStatementWithIndent[] = $line;
    }

    return implode("\n", $sqlStatementWithIndent);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
