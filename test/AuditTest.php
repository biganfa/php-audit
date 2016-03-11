<?php
//----------------------------------------------------------------------------------------------------------------------
use SetBased\Audit;

//----------------------------------------------------------------------------------------------------------------------
class AuditTest extends PHPUnit_Extensions_Database_TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the test dataset.
   *
   * @return PHPUnit_Extensions_Database_DataSet_IDataSet
   */
  protected function getDataSet()
  {
    return $this->createFlatXMLDataSet(dirname(__FILE__).'/rank_data.xml');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @return PHPUnit_Extensions_Database_DB_DefaultDatabaseConnection
   */
  protected function getConnection()
  {
    $config       = $this->readConfigFile('etc/audit.json');
    $host_name    = $config['database']['host_name'];
    $user_name    = $config['database']['user_name'];
    $password     = $config['database']['password'];
    $data_schema  = $config['database']['data_schema'];
    $audit_schema = $config['database']['audit_schema'];

    $pdo = new PDO("mysql:host=$host_name;dbname=$data_schema", $user_name, $password);

    return $this->createDefaultDBConnection($pdo, 'test_db');
  }
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Reads configuration parameters from the configuration file.
   *
   * @param string $theConfigFilename
   *
   * @return array Config file
   * @throws \SetBased\Audit\Exception\RuntimeException
   */
  public function readConfigFile($theConfigFilename)
  {
    $content = file_get_contents($theConfigFilename);
    if ($content===false)
    {
      throw new RuntimeException("Unable to read file '%s'.", $theConfigFilename);
    }
    $config = json_decode($content, true);

    foreach ($config['tables'] as $table_name => $flag)
    {
      $config['tables'][$table_name] = filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    return $config;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /** @test */
  public function testWork()
  {
//    $this->assertEquals(1, 1);
//    $this->assertEquals(2, 1);
//    $this->assertEquals(3, 1);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//--------------------------------------------------------------------------------------------------------------------//--------------------------------------------------------------------------------------------------------------------