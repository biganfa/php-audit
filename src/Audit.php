<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SetBased\Audit\MySql\DataLayer;

//----------------------------------------------------------------------------------------------------------------------
/**
 * The PhpAudit program.
 */
class Audit
{
  //--------------------------------------------------------------------------------------------------------------------.
  /**
   * Array of tables from audit schema.
   *
   * @var array
   */
  private $myAuditSchemaTables;

  /**
   * All config file as array.
   *
   * @var array
   */
  private $myConfig;

  /**
   * Config file name.
   *
   * @var string
   */
  private $myConfigFileName;

  /**
   * Array of tables from data schema.
   *
   * @var array
   */
  private $myDataSchemaTables;

  /**
   * Logger.
   *
   * @var Logger
   */
  private $myLog;

  /**
   * If true remove all column information from config file.
   *
   * @var boolean
   */
  private $myPruneOption;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compares the tables listed in the config file and the tables found in the audit schema
   *
   * @param string  $theTableName Name of table
   * @param Columns $theColumns   The table columns.
   */
  public function getColumns($theTableName, $theColumns)
  {
    $columns = [];
    foreach ($theColumns->getColumns() as $column)
    {
      $columns[] = ['column_name' => $column['column_name'],
                    'column_type' => $column['column_type']];
    }
    $this->myConfig['table_columns'][$theTableName] = $columns;

    if ($this->myPruneOption)
    {
      $this->myConfig['table_columns'] = [];
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Getting list of all tables from information_schema of database from config file.
   */
  public function listOfTables()
  {
    $this->myDataSchemaTables = DataLayer::getTablesNames($this->myConfig['database']['data_schema']);

    $this->myAuditSchemaTables = DataLayer::getTablesNames($this->myConfig['database']['audit_schema']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Log function
   *
   * @param string $theMessage Message for print in console
   */
  public function logInfo($theMessage)
  {
    $this->myLog->addNotice($theMessage);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Log verbose
   *
   * @param string $theMessage Message for print in console
   */
  public function logVerbose($theMessage)
  {
    $this->myLog->addInfo($theMessage);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main function.
   *
   * @param string[] $theOptions Option from console on running script
   *
   * @return int
   */
  public function main($theOptions)
  {
    $this->myPruneOption = (boolean)$theOptions['prune'];

    // Initialize Monolog, set custom output for LineFormatter
    // Set Logger levels from console commands {-v, -d}
    $output    = "[%datetime%] %message%\n";
    $formatter = new LineFormatter($output);

    $logger_level = Logger::NOTICE;
    if ($theOptions['verbose']) $logger_level = Logger::INFO;
    if ($theOptions['debug']) $logger_level = Logger::DEBUG;

    $streamHandler = new StreamHandler('php://stdout', $logger_level);
    $streamHandler->setFormatter($formatter);
    $this->myLog = new Logger('AUDIT');
    $this->myLog->pushHandler($streamHandler);
    $streamHandler->setFormatter($formatter);

    // Read config file name, config content and then save in variable
    $this->myConfigFileName = $theOptions['config'];
    $this->readConfigFile($this->myConfigFileName);

    // Create database connection with params from config file
    DataLayer::connect($this->myConfig['database']['host_name'], $this->myConfig['database']['user_name'],
                       $this->myConfig['database']['password'], $this->myConfig['database']['data_schema']);
    DataLayer::setLog($this->myLog);
    DataLayer::setAdditionalSQL($this->myConfig['additional_sql']);

    $this->listOfTables();

    $this->unknownTables();

    foreach ($this->myDataSchemaTables as $table)
    {
      if ($this->myConfig['tables'][$table['table_name']]['audit'])
      {
        $tableColumns = [];
        if (isset($this->myConfig['table_columns'][$table['table_name']]))
        {
          $tableColumns = $this->myConfig['table_columns'][$table['table_name']];
        }
        $currentTable = new Table($table['table_name'],
                                   $this->myLog,
                                   $this->myConfig['database']['data_schema'],
                                   $this->myConfig['database']['audit_schema'],
                                   $tableColumns,
                                   $this->myConfig['audit_columns'],
                                   $this->myConfig['tables'][$table['table_name']]['alias'],
                                   $this->myConfig['tables'][$table['table_name']]['skip']);
        $res           = DataLayer::searchInRowSet('table_name', $currentTable->getTableName(), $this->myAuditSchemaTables);
        if (!isset($res))
        {
          $currentTable->createMissingAuditTable();
        }

        $columns = $currentTable->main();
        if (empty($columns['altered_columns']))
        {
          $this->getColumns($currentTable->getTableName(), $columns['full_columns']);
        }
      }
    }

    // Drop database connection
    DataLayer::disconnect();

    $this->rewriteConfig();

    return 0;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Reads configuration parameters from the configuration file.
   *
   * @param string $theConfigFilename
   */
  public function readConfigFile($theConfigFilename)
  {
    $content = file_get_contents($theConfigFilename);

    $this->myConfig = json_decode($content, true);

    if (!isset($this->myConfig['audit_columns']))
    {
      $this->myConfig['audit_columns'] = [];
    }

    foreach ($this->myConfig['tables'] as $table_name => $params)
    {
      $this->myConfig['tables'][$table_name]['audit'] = filter_var($params['audit'], FILTER_VALIDATE_BOOLEAN);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Found tables in config file
   *
   * Compares the tables listed in the config file and the tables found in the data schema
   */
  public function unknownTables()
  {
    foreach ($this->myDataSchemaTables as $table)
    {
      if (isset($this->myConfig['tables'][$table['table_name']]))
      {
        if (!isset($this->myConfig['tables'][$table['table_name']]['audit']))
        {
          $this->logInfo(sprintf('Audit flag is not set in table %s.', $table['table_name']));
        }
        else
        {
          if ($this->myConfig['tables'][$table['table_name']]['audit'])
          {
            $this->myConfig['tables'][$table['table_name']]['alias'] = Table::getRandomAlias();
          }
        }
      }
      else
      {
        $this->logInfo(sprintf('Find new table %s, not listed in config file.', $table['table_name']));
        $this->myConfig['tables'][$table['table_name']] = ['audit' => false,
                                                           'alias' => null,
                                                           'skip'  => null];
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Write new data to config file.
   */
  private function rewriteConfig()
  {
    Util::writeTwoPhases($this->myConfigFileName, json_encode($this->myConfig, JSON_PRETTY_PRINT));
  }

  //--------------------------------------------------------------------------------------------------------------------

}

//----------------------------------------------------------------------------------------------------------------------
