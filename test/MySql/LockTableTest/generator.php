#!/usr/bin/env php
<?php
//----------------------------------------------------------------------------------------------------------------------
use SetBased\Affirm\ErrorHandler;
use SetBased\Stratum\MySql\StaticDataLayer;

//----------------------------------------------------------------------------------------------------------------------
$files = [__DIR__.'/../vendor/autoload.php',
          __DIR__.'/../../vendor/autoload.php',
          __DIR__.'/../../../vendor/autoload.php',
          __DIR__.'/../../../../vendor/autoload.php'];

foreach ($files as $file)
{
  if (file_exists($file))
  {
    require $file;
    break;
  }
}

//----------------------------------------------------------------------------------------------------------------------
/**
 * Returns the error string of the last json_encode() or json_decode() call.
 *
 * json_last_error_msg is defined in php 5.5.
 */
if (!function_exists('json_last_error_msg'))
{
  function json_last_error_msg()
  {
    static $errors = [
      JSON_ERROR_NONE           => null,
      JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
      JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
      JSON_ERROR_CTRL_CHAR      => 'Unexpected control character found',
      JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON',
      JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    ];
    $error = json_last_error();

    return array_key_exists($error, $errors) ? $errors[$error] : "Unknown error ({$error})";
  }
}

declare(ticks = 1);

//----------------------------------------------------------------------------------------------------------------------
function signalHandler()
{
  $GLOBALS['exit'] = true;
}

//----------------------------------------------------------------------------------------------------------------------
$GLOBALS['exit'] = false;

pcntl_signal(SIGUSR1, "signalHandler");

// Set error handler.
$handler = new ErrorHandler();
$handler->register();
StaticDataLayer::connect('localhost', 'test', 'test', 'test_data');

$i = 0;
while (true)
{
  if ($GLOBALS['exit']) break;

  StaticDataLayer::executeNone('insert into TABLE1(c) values(1)');
  StaticDataLayer::executeNone('update TABLE1 set c = 2');
  StaticDataLayer::executeNone('delete from TABLE1 where c = 2');

  $i++;
}

StaticDataLayer::commit();
StaticDataLayer::disconnect();

echo $i;