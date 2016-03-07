<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

use SetBased\Audit\Exception\RuntimeException;
use SetBased\Audit\MySql\DataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class.
 */
class Audit
{
  //--------------------------------------------------------------------------------------------------------------------.
  /**
   * All config file as array.
   *
   * @var array
   */
  private $myConfig;

  /**
   * If true remove all column information from config file.
   *
   * @var boolean
   */
  private $myPruneOption;

  /**
   * Array of tables from audit schema.
   *
   * @var array
   */
  private $myAuditSchemaTables;

  /**
   * Array of tables from data schema.
   *
   * @var array
   */
  private $myDataSchemaTables;

  /**
   * Config file name.
   *
   * @var string
   */
  private $myConfigFileName;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main function .
   *
   * @param string  $theConfigFilename
   *
   * @param boolean $thePruneOption
   *
   * @return int
   */
  public function main($theConfigFilename, $thePruneOption)
  {
    $this->myConfigFileName = $theConfigFilename;

    $this->myPruneOption = $thePruneOption;

    $this->readConfigFile($theConfigFilename);

    // Create database connection with params from config file
    DataLayer::connect($this->myConfig['database']['host_name'], $this->myConfig['database']['user_name'],
                       $this->myConfig['database']['password'], $this->myConfig['database']['database_name']);

    $this->listOfTables();

    $this->getUnknownTables();

    $this->getKnownTables();

    $this->getColumns();

    $this->generateSqlCreateTable();

    $this->findDifferentColumns();

    // Drop database connection
    DataLayer::disconnect();

    return 0;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares the tables listed in the config file and the tables found in the audit schema
   */
  public function getColumns()
  {
    $this->myConfig['table_columns'] = [];
    foreach ($this->myConfig['tables'] as $table_name => $flag)
    {
      if (filter_var($flag, FILTER_VALIDATE_BOOLEAN))
      {
        $columns = $this->columnsOfTable($table_name);
        foreach ($columns as $column)
        {
          $this->myConfig['table_columns'][$table_name][$column['column_name']] = $column['data_type'];
        }
      }
    }
    if ($this->myPruneOption==1)
    {
      $this->myConfig['table_columns'] = [];
    }
    $this->rewriteConfig();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Generate sql code for creating table with merging columns.
   *
   * @return array
   */
  public function generateSqlCreateTable()
  {
    foreach ($this->myConfig['tables'] as $table_name => $flag)
    {
      if (filter_var($flag, FILTER_VALIDATE_BOOLEAN))
      {
        $sql_create = "CREATE TABLE AUD_{$table_name} (";
        $columns    = $this->getMergeColumns($table_name, true);
        foreach ($columns as $column)
        {
          $sql_create .= $column['column_name'].' '.$column['data_type'].",";
        }
        $sql_create .= ");";

        print_r($sql_create);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Finding different columns from new audit table and existing.
   *
   * @return array
   */
  public function findDifferentColumns()
  {
    foreach ($this->myConfig['tables'] as $table_name => $flag)
    {
      if (filter_var($flag, FILTER_VALIDATE_BOOLEAN))
      {
        $new_columns      = $this->getMergeColumns($table_name, false);
        $existing_columns = DataLayer::getTableColumns('rank_audit', 'ABC_BLOB');
        $diff_columns     = $this->array_diff_assoc_recursive($new_columns, $existing_columns);
//        print_r($new_columns);
//        print_r($existing_columns);
//        print_r($diff_columns);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Return different values from two multidimensional arrays.
   *
   * @param $array1
   * @param $array2
   *
   * @return array
   */
  function array_diff_assoc_recursive($array1, $array2)
  {
    foreach ($array1 as $key => $value)
    {
      if (is_array($value))
      {
        if (!isset($array2[$key]))
        {
          $difference[$key] = $value;
        }
        elseif (!is_array($array2[$key]))
        {
          $difference[$key] = $value;
        }
        else
        {
          $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
          if ($new_diff!=false)
          {
            $difference[$key] = $new_diff;
          }
        }
      }
      elseif (!isset($array2[$key]) || $array2[$key]!=$value)
      {
        $difference[$key] = $value;
      }
    }

    return !isset($difference) ? 0 : $difference;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Generate array with audit columns and columns from data table.
   *
   * @param $theTableName  string name of table
   *
   * @param $flagDefault   boolean flag for add or not 'DEFAULT NULL' to data_type
   *
   * @return array
   */
  public function getMergeColumns($theTableName, $flagDefault)
  {
    $columns = [];
    foreach ($this->myConfig['audit_columns'] as $column)
    {
      $columns[] = ['column_name' => $column['name'], 'data_type' => $column['type']];
    }
    foreach ($this->myConfig['table_columns'][$theTableName] as $name => $type)
    {
      $columns[] = ['column_name' => $name, 'data_type' => ($flagDefault ? $type.' DEFAULT NULL' : $type)];
    }

    return $columns;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares the tables listed in the config file and the tables found in the audit schema
   */
  public function getKnownTables()
  {
    foreach ($this->myConfig['tables'] as $table_name => $flag)
    {
      if (filter_var($flag, FILTER_VALIDATE_BOOLEAN))
      {
        if ($this->searchInMultidimensionalArray($table_name, $this->myAuditSchemaTables))
        {
          // @todo comparing the audit and data table
        }
        else
        {
          // @todo comparing the audit and data table
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check isset value in multidimensional array
   *
   * @param $needle   mixed Value for search
   * @param $haystack array Multidimensional array
   *
   * @return bool
   */
  private function searchInMultidimensionalArray($needle, $haystack)
  {
    if (in_array($needle, $haystack))
    {
      return true;
    }
    foreach ($haystack as $element)
    {
      if (is_array($element) && $this->searchInMultidimensionalArray($needle, $element))
        return true;
    }

    return false;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares the tables listed in the config file and the tables found in the data schema
   */
  public function getUnknownTables()
  {
    foreach ($this->myDataSchemaTables as $key => $table)
    {
      if (isset($this->myConfig['tables'][$table['table_name']]))
      {
        if (filter_var($this->myConfig['tables'][$table['table_name']], FILTER_VALIDATE_BOOLEAN)!=true)
        {
          print_r("Audit flag is not set in table {$table['table_name']}.\n");
        }
      }
      else
      {
        print_r("Find new tableb{$table['table_name']}, not listed in config file.\n");
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting list of all tables from information_schema of database from config file.
   */
  public function listOfTables()
  {
    $this->myDataSchemaTables = DataLayer::getTables('rank_data');

    $this->myAuditSchemaTables = DataLayer::getTables('rank_audit');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting columns names and data_types from table from information_schema of database from config file.
   *
   * @param $theTableName
   *
   * @return array
   */
  public function columnsOfTable($theTableName)
  {
    $result = DataLayer::getTableColumns('rank_data', $theTableName);

    return $result;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Reads configuration parameters from the configuration file.
   *
   * @param string $theConfigFilename
   *
   * @throws \SetBased\Audit\Exception\RuntimeException
   */
  public function readConfigFile($theConfigFilename)
  {
    $content = file_get_contents($theConfigFilename);
    if ($content===false)
    {
      throw new RuntimeException("Unable to read file '%s'.", $theConfigFilename);
    }
    $this->myConfig = json_decode($content, true);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Write new data to config file.
   */
  private function rewriteConfig()
  {
    Util::writeTwoPhases($this->myConfigFileName, json_encode($this->myConfig,JSON_PRETTY_PRINT));
  }

  //--------------------------------------------------------------------------------------------------------------------

}

//----------------------------------------------------------------------------------------------------------------------
