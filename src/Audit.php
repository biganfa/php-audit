<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\Audit;

use Monolog\Formatter\LineFormatter;
use SetBased\Audit\Exception\RuntimeException;
use SetBased\Audit\MySql\DataLayer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

//----------------------------------------------------------------------------------------------------------------------
/**
 * The PhpAudit program.
 */
class Audit
{
  //--------------------------------------------------------------------------------------------------------------------.
  /**
   * Logger.
   *
   * @var Logger
   */
  private $myLog;

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

    $this->listOfTables();

    $this->unknownTables();

    foreach ($this->myDataSchemaTables as $table)
    {
      if ($this->myConfig['tables'][$table['table_name']]['audit'])
      {
        $table_columns = [];
        if (isset($this->myConfig['table_columns'][$table['table_name']]))
        {
          $table_columns = $this->myConfig['table_columns'][$table['table_name']];
        }
        $current_table = new Table($table['table_name'],
                                   $this->myLog,
                                   $this->myConfig['database']['data_schema'],
                                   $this->myConfig['database']['audit_schema'],
                                   $table_columns,
                                   $this->myConfig['audit_columns'],
                                   $this->myConfig['tables'][$table['table_name']]['alias']);
        $res           = DataLayer::searchInRowSet('table_name', $current_table->getTableName(), $this->myAuditSchemaTables);
        if (!isset($res))
        {
          $current_table->createMissingAuditTable();
        }

        $columns = $current_table->main();
        $this->getColumns($current_table->getTableName(), $columns);
      }
    }

    // Drop database connection
    DataLayer::disconnect();

    $this->rewriteConfig();

    return 0;
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
   * Found tables in config file
   *
   * Compares the tables listed in the config file and the tables found in the data schema
   */
  public function unknownTables()
  {
    foreach ($this->myDataSchemaTables as $table)
    {
      if (isset($this->myConfig['tables'][$table['table_name']]['audit']))
      {
        if (!isset($this->myConfig['tables'][$table['table_name']]['audit']))
        {
          $this->logInfo(sprintf('Audit flag is not set in table %s.', $table['table_name']));
        }
      }
      else
      {
        $this->logInfo(sprintf('Find new table %s, not listed in config file.', $table['table_name']));
        $this->myConfig['tables'][$table['table_name']] = ['audit' => false,
                                                           'alias' => Table::getUUID()];
      }
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
   * Reads configuration parameters from the configuration file.
   *
   * @param string $theConfigFilename
   *
   * @throws RuntimeException
   */
  public function readConfigFile($theConfigFilename)
  {
    $content = file_get_contents($theConfigFilename);
    if ($content===false)
    {
      throw new RuntimeException("Unable to read file '%s'.", $theConfigFilename);
    }
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
   * Write new data to config file.
   */
  private function rewriteConfig()
  {
    Util::writeTwoPhases($this->myConfigFileName, json_encode($this->myConfig, JSON_PRETTY_PRINT));
  }

  //--------------------------------------------------------------------------------------------------------------------

}

//----------------------------------------------------------------------------------------------------------------------
