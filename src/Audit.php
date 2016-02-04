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
   * @param string $theConfigFilename
   *
   * @return int
   */
  public function main($theConfigFilename)
  {
    $this->myConfigFileName = $theConfigFilename;

    $this->readConfigFile($theConfigFilename);

    // Create database connection with params from config file
    DataLayer::connect($this->myConfig['database']['host_name'], $this->myConfig['database']['user_name'],
                       $this->myConfig['database']['password'], $this->myConfig['database']['database_name']);

    $this->listOfTables();

    $this->compareTables();

    $this->compareAuditTables();

    $this->columnsOfTable();

    // Drop database connection
    DataLayer::disconnect();

    return 0;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares the tables listed in the config file and the tables found in the audit schema
   */
  public function compareAuditTables()
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
  public function compareTables()
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
        print_r("Find new table {$table['table_name']}, not listed in config file.\n");
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting list of all tables from information_schema of database from config file.
   */
  public function listOfTables()
  {
    $this->myDataSchemaTables = DataLayer::getTablesNames('rank_data');

    $this->myAuditSchemaTables = DataLayer::getTablesNames('rank_audit');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting list of all tables from information_schema of database from config file.
   */
  public function columnsOfTable()
  {
    $result = DataLayer::getTableColumns('rank_data', 'RNK_SYSTEM');

//    var_dump($result);
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
    Util::writeTwoPhases($this->myConfigFileName, json_encode($this->myConfig));
  }

  //--------------------------------------------------------------------------------------------------------------------

}

//----------------------------------------------------------------------------------------------------------------------
