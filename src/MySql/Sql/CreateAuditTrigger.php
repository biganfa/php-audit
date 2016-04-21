<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

use SetBased\Affirm\Exception\FallenException;
use SetBased\Audit\Columns;
use SetBased\Audit\MySql\DataLayer;
use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for creating and executing SQL statements for Audit triggers.
 */
class CreateAuditTrigger extends DataLayer
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a trigger on a table.
   *
   * @param string  $theDataSchema      The name of the data schema.
   * @param string  $theAuditSchemaName The name of the audit schema.
   * @param string  $theTableName       The name of the table.
   * @param string  $theAction          Action for trigger {INSERT, UPDATE, DELETE}
   * @param string  $theTriggerName     The name of the trigger.
   * @param string  $theSkipVariable    The skip variable.
   * @param Columns $theTableColumns    Table columns from metadata.
   * @param Columns $theAuditColumns    Audit columns from metadata.
   *
   * @throws FallenException
   */
  public static function buildStatement($theDataSchema,
                                        $theAuditSchemaName,
                                        $theTableName,
                                        $theAction,
                                        $theTriggerName,
                                        $theSkipVariable,
                                        $theTableColumns,
                                        $theAuditColumns)
  {
    $rowState = [];
    switch ($theAction)
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
        throw new FallenException('action', $theAction);
    }

    $sql = sprintf('
create trigger %s
after %s on `%s`.`%s`
for each row
begin
',
                   $theTriggerName,
                   $theAction,
                   $theDataSchema,
                   $theTableName);

    $sql .= self::skipStatement($theSkipVariable);

    foreach (self::$ourAdditionalSql as $line)
    {
      $sql .= $line;
    }
    $sql .= self::createInsertStatement($theAuditSchemaName,
                                        $theTableName,
                                        $theAuditColumns,
                                        $theTableColumns,
                                        $theAction,
                                        $rowState[0]);

    if ($theAction=='UPDATE')
    {
      $sql .= self::createInsertStatement($theAuditSchemaName,
                                          $theTableName,
                                          $theAuditColumns,
                                          $theTableColumns,
                                          $theAction,
                                          $rowState[1]);
    }
    $sql .= isset($theSkipVariable) ? 'end if;' : '';
    $sql .= 'end;';

    self::executeNone($sql);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an insert SQL statement for an audit table.
   *
   * @param string  $theSchemaName   The name of the database schema.
   * @param string  $theTableName    The name of the table.
   * @param Columns $theAuditColumns Audit columns from metadata.
   * @param Columns $theTableColumns Table columns from metadata.
   * @param string  $theAction       Action for trigger {INSERT, UPDATE, DELETE}
   * @param string  $theRowState     Row state for values in insert statement {NEW, OLD}
   *
   * @return string
   */
  private static function createInsertStatement($theSchemaName,
                                                $theTableName,
                                                $theAuditColumns,
                                                $theTableColumns,
                                                $theAction,
                                                $theRowState)
  {
    $columnNames = '';
    foreach ($theAuditColumns->getColumns() as $column)
    {
      if ($columnNames) $columnNames .= ',';
      $columnNames .= $column['column_name'];
    }
    foreach ($theTableColumns->getColumns() as $column)
    {
      if ($columnNames) $columnNames .= ',';
      $columnNames .= $column['column_name'];
    }

    $values = '';
    foreach ($theAuditColumns->getColumns() as $column)
    {
      if ($values) $values .= ',';
      if (isset($column['audit_value_type']))
      {
        switch ($column['audit_value_type'])
        {
          case 'ACTION':
            $values .= StaticDataLayer::quoteString($theAction);
            break;

          case 'STATE':
            $values .= StaticDataLayer::quoteString($theRowState);
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
    foreach ($theTableColumns->getColumns() as $column)
    {
      if ($values) $values .= ',';
      $values .= sprintf('%s.`%s`', $theRowState, $column['column_name']);
    }

    $insertStatement = sprintf('
insert into `%s`.`%s`(%s)
values(%s);',
                                $theSchemaName,
                                $theTableName,
                                $columnNames,
                                $values);

    return $insertStatement;
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
      $statement = sprintf('if (%s is null) then', $theSkipVariable);
    }

    return $statement;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
