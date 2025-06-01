<?php

namespace Fallen\SecondLife\Classes;

/**
 * @author Fallen Kiyori <fallen.Kiyori@gmail.com>
 * @copyright 2023 FKS
 * @license https://mit-license.org/ MIT
 * 
 * @method mixed connect(string $driver, string $host, string $database, string $username, string $password)
 */
class Db
{
  /**
   * Establish a connection to the database
   *
   * @param string $driver
   * @param string $host
   * @param string $database
   * @param string $username
   * @param string $password
   * @return mixed PDO object or throws  
   */


  
  public static function connect(string $driver, string $host, string $database, string $username, string $password)
  {
    $dsn = sprintf("%s:dbname=%s;host=%s", $driver, $database, $host);
    return new \PDO($dsn, $username, $password, [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
  }
}