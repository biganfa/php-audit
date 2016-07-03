<?php
//----------------------------------------------------------------------------------------------------------------------
error_reporting(E_ALL);
date_default_timezone_set('Europe/Amsterdam');

ini_set('memory_limit', '10000M');

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

//----------------------------------------------------------------------------------------------------------------------
