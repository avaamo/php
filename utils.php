<?php
  /**
    *   fileName: utils.php
    *   createdAt: May 24, 2016
    *   Author: Jebin
    *   Description: Utils helper for utility methods
   */

  class Utils {
    function __construct() {}

    //http://stackoverflow.com/a/15875555/407342
    public static function guidv4() {
      $data = openssl_random_pseudo_bytes(16);

      $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
      $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

      return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
  }

  class Logger {
    public $logger = false;

    function __construct($logger) {
      $this->logger = $logger;
    }

    public function printLogs() {
      if($this->logger == true) {
        foreach (func_get_args() as $arg) {
          if(is_string($arg) == true) {
            echo $arg."\n";
          } else {
            print_r($arg);
            echo "\n";
          }
        }
      }
    }
  }
?>
