<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Sql;

use SetBased\Audit\MySql\DataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for creating and executing SQL statements for creating audit tables.
 */
class CreateAuditTable extends DataLayer
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates an audit table.
   *
   * @param string $theAuditSchemaName The name of the audit schema.
   * @param string $theTableName       The name of the table.
   * @param array  $theMergedColumns   The metadata of the columns of the audit table (i.e. the audit columns and
   *                                   columns of the data table).
   */
  public static function buildStatement($theAuditSchemaName, $theTableName, $theMergedColumns)
  {
    $sqlCreate = sprintf('create table `%s`.`%s` (', $theAuditSchemaName, $theTableName);
    foreach ($theMergedColumns as $column)
    {
      $sqlCreate .= sprintf('`%s` %s', $column['column_name'], $column['column_type']);
      if (end($theMergedColumns)!==$column)
      {
        $sqlCreate .= ',';
      }
    }
    $sqlCreate .= ')';

    self::executeNone($sqlCreate);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
