<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit\MySql\Helper;


//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit\ColumnTypes;

/**
 * A helper class for column types.
 */
class RowHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create table row.
   *
   * @param array[] $theRow Data for table row.
   *
   * @return \string
   */
  public static function createColumnOptionsRow($theRow)
  {
    $dataCharsetName   = isset($theRow['data_character_set_name']) ? $theRow['data_character_set_name'] : null;
    $dataCollationName = isset($theRow['data_collation_name']) ? $theRow['data_collation_name'] : null;

    $auditCharsetName   = isset($theRow['audit_character_set_name']) ? $theRow['audit_character_set_name'] : null;
    $auditCollationName = isset($theRow['audit_collation_name']) ? $theRow['audit_collation_name'] : null;


    $tableRow = ['column_name'      => null,
                 'data_table_type'  => self::styledOptionsRow($dataCharsetName, $dataCollationName),
                 'audit_table_type' => self::styledOptionsRow($auditCharsetName, $auditCollationName),
                 'config_type'      => null];

    return $tableRow;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create table row.
   *
   * @param $theCharacterSetName
   * @param $theCollationName
   *
   * @return \string
   */
  public static function styledOptionsRow($theCharacterSetName, $theCollationName)
  {
    $charsetName   = isset($theCharacterSetName) ? '['.$theCharacterSetName.']' : null;
    $collationName = isset($theCollationName) ? '['.$theCollationName.']' : null;

    return trim(sprintf('%s %s', $charsetName, $collationName));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create table row.
   *
   * @param array[] $theRow Data for table row.
   *
   * @return \array[]
   */
  public static function createTableRow($theRow)
  {
    $tableRow = ['column_name'      => isset($theRow['column_name']) ? $theRow['column_name'] : null,
                 'data_table_type'  => isset($theRow['data_column_type']) ? $theRow['data_column_type'] : null,
                 'audit_table_type' => isset($theRow['audit_column_type']) ? $theRow['audit_column_type'] : null,
                 'config_type'      => isset($theRow['config_column_type']) ? $theRow['config_column_type'] : null];

    var_dump($tableRow);

    return $tableRow;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check isset options(collation, character set name) from row.
   *
   * @param array[] $theRow Row for append.
   *
   * @return bool
   */
  private static function checkOptions($theRow)
  {
    if (isset($theRow['audit_character_set_name']))
    {
      return true;
    }
    if (isset($theRow['data_character_set_name']))
    {
      return true;
    }
    if (isset($theRow['audit_collation_name']))
    {
      return true;
    }
    if (isset($theRow['data_collation_name']))
    {
      return true;
    }

    return false;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Append a row.
   *
   * @param \array[]    $theExistRows Exist rows array for appending.
   * @param ColumnTypes $theRow       Row for append.
   */
  public static function appendRow(&$theExistRows, $theRow)
  {
    $theRow         = $theRow->getTypes();
    $theExistRows[] = self::createTableRow($theRow);
    if (self::checkOptions($theRow))
    {
      $theRow         = self::createColumnOptionsRow($theRow);
      $theExistRows[] = $theRow;
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
