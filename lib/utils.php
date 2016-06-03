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

    public static function GET($url)  {
      $opts = array(
        'http'=>array(
          'method'=>"GET",
          'header'=>"ACCESS-TOKEN: ".Avaamo::$ACCESS_TOKEN
        )
      );
      return file_get_contents($url, false, stream_context_create($opts));
    }

    public static function POST($post_data, $url = false, $content_type = "multipart/form-data") {
      $url = $url ? $url : API::$APP_SERVER_MESSAGE;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
      curl_setopt($ch, CURLOPT_POST, count($post_data));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_VERBOSE, 0);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array("ACCESS-TOKEN: ".Avaamo::$ACCESS_TOKEN, "Content-Type: $content_type"));
      $response = curl_exec($ch);
      $info = curl_getinfo($ch);
      Logger::printLogs("Request status: ".$info["http_code"]);
      curl_close($ch);
      return $response;
    }

    public static function download($url, $path, $permission = 0777, $name = "") {
      Logger::printLogs("Download initiated", "Path: ".$path, "Given Name: ".$name);
      if(!$path) {
        throw new Exception("No path provided for download");
      }
      $name = $name ? $name : $this->name;
      if (!file_exists($path)) {
        Logger::printLogs("File path $path doesn't exist. Creating one with $permission");
        mkdir($path, $permission, true);
      }
      $file = $path."/".$name;
      Logger::printLogs("Downloading to $file");

      file_put_contents($file, Utils::GET($url));
      Logger::printLogs("Download completed");
    }
  }

  class Logger {
    function __construct() {}

    public static function printLogs() {
      if(Avaamo::$DEBUG == true) {
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

  class API {
    // public static $DS_SERVER_HOST = "http://localhost:4000";
    // public static $APP_SERVER_HOST = "http://localhost:3000";

    public static $DS_SERVER_HOST = "https://ds.avaamo.com";
    public static $APP_SERVER_HOST = "https://prod.avaamo.com/s";

    public static $APP_SERVER_MESSAGE = "/v1/messages.json";
    public static $APP_SERVER_FILE = "/files.json";
    public static $APP_SERVER_READ_ACK = "/messages/read_ack";


    private function __construct() {}

    public static function init() {
      self::$APP_SERVER_MESSAGE = self::$APP_SERVER_HOST.self::$APP_SERVER_MESSAGE;
      self::$APP_SERVER_FILE = self::$APP_SERVER_HOST.self::$APP_SERVER_FILE;
      self::$APP_SERVER_READ_ACK = self::$APP_SERVER_HOST.self::$APP_SERVER_READ_ACK;
    }

    public static function getFile($id) {
      if(!$id) {
        throw new Exception("No file id provided");
      }
      return self::$APP_SERVER_HOST."/files/$id.json";
    }

    public static function getFormResponse($uuid) {
      if(!$uuid) {
        throw new Exception("No file id provided");
      }
      return self::$APP_SERVER_HOST."/form/responses/$uuid.json";;
    }
  }
?>
