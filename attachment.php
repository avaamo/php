<?php
  /**
    *   fileName: attachment.php
    *   createdAt: May 24, 2016
    *   Author: Jebin
    *   Description: Classes to create attachment objects and send to conversation
   */

  class Attachment {
    protected $APP_API = "/v1/messages.json";
    protected $ATTACHMENT_API = "/files.json";

    public function __construct($bot_uuid, $access_token, $logger = false) {
      $this->bot_uuid = $bot_uuid;
      $this->access_token = $access_token;

      $this->APP_API = APP_SERVER_HOST.$this->APP_API;
      $this->ATTACHMENT_API = APP_SERVER_HOST.$this->ATTACHMENT_API;

      $this->logger = new Logger($logger);
    }

    protected function encode($path) {
      $encoded_path = "";
      if(stripos($path, "http://") === 0) {
        $split_path = parse_url($path);
        foreach ($split_path as $key => $value) {
          if($key == "query" || $key == "fragment") {
            $encoded_path .= urlencode($val);
          } else {
            $encoded_path .= $val;
          }
        }
      } else {
        $encoded_path = $path;
      }
      $this->logger->printLogs("Path to fetch image: $encoded_path");
      return $encoded_path;
    }

    protected function getFileMeta($path) {
      $path = $this->encode($path);

      $fileHandler = fopen($path, "rb");
      $fileName = $this->getFileName($path);
      try {
        $fileSize = strlen(stream_get_contents($fileHandler));
      } catch(Exception $e) {
        $fileSize = filesize($path);
      }
      //TODO: content type for external files
      $contentType = mime_content_type($path);//$this->getContentType($fileHandler);

      $this->logger->printLogs("File details: Name: $fileName, Size: $fileSize, Content-Type: $contentType");
      return array("file_size" => $fileSize, "content_type" => $contentType, "file_name" => $fileName);
    }

    public function post($post_data, $url = false, $content_type = "multipart/form-data") {
      $url = $url ? $url : $this->APP_API;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
      curl_setopt($ch, CURLOPT_POST, count($post_data));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_VERBOSE, $this->logger->logger);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array("ACCESS-TOKEN: $this->access_token", "Content-Type: $content_type"));
      $response = curl_exec($ch);
      curl_close($ch);
      return $response;
    }

    protected function getFileName($path) {
      $path_explode = explode("/", $path);
      return $path_explode[count($path_explode) - 1];
    }

    protected function getContentType($fp) {
      $meta = stream_get_meta_data($fp);
      return "image/png";
    }
  }

  class ImageAttachment extends Attachment {

    public function __construct($bot_uuid, $access_token, $logger = false) {
      parent::__construct($bot_uuid, $access_token, $logger);
    }

    public function send($path, $content = "", $conversation_uuid) {
      $meta = $this->getFileMeta($path);
      $post_data = array(
        "[message][conversation][uuid]" => $conversation_uuid,
        "[message][user][layer_id]" => $this->bot_uuid,
        "[message][attachments][files][][uid]" => Utils::guidv4(),
        "[message][attachments][files][][type]" => $meta["content_type"],
        "[message][attachments][files][][name]" => $meta["file_name"],
        "[message][attachments][files][][size]" => $meta["file_size"],
        "[message][attachments][files][][data]" => new CURLFile($path, $meta["content_type"], $meta["file_name"]),
        "[message][content]" => $content,
        "[message][content_type]" => "photo",
        "[message][uuid]" => Utils::guidv4(),
        "[message][created_at]" => microtime(true)
      );
      $this->post($post_data);
      $this->logger->printLogs("Image posted");
    }
  }

  class FileAttachment extends Attachment {

    public function __construct($bot_uuid, $access_token, $logger = false) {
      parent::__construct($bot_uuid, $access_token, $logger);
    }

    public function send($path, $conversation_uuid) {
      $meta = $this->getFileMeta($path);
      $post_data = array(
        "[message][conversation][uuid]" => $conversation_uuid,
        "[message][user][layer_id]" => $this->bot_uuid,
        "[message][attachments][files][][uid]" => Utils::guidv4(),
        "[message][attachments][files][][type]" => $meta["content_type"],
        "[message][attachments][files][][name]" => $meta["file_name"],
        "[message][attachments][files][][size]" => $meta["file_size"],
        "[message][attachments][files][][data]" => new CURLFile($path, $meta["content_type"], $meta["file_name"]),
        "[message][content]" => $content,
        "[message][content_type]" => "file",
        "[message][uuid]" => Utils::guidv4()
      );
      $this->post($post_data);
      $this->logger->printLogs("File posted");
    }

  }

  class CardAttachment extends Attachment {

    public function __construct($bot_uuid, $access_token, $logger = false) {
      parent::__construct($bot_uuid, $access_token, $logger);
    }

    private function postImageAndGetID($path) {
      $meta = $this->getFileMeta($path);
      $post_data = array("data" => new CURLFile($path, $meta["content_type"], $meta["file_name"]));
      $response = json_decode($this->post($post_data, $this->ATTACHMENT_API));
      return $response->file->uuid;
    }

    public function send($card, $content = "", $conversation_uuid) {
      $card->showcase_image_uuid = $this->postImageAndGetID($card->showcase_image_path);

      $post_data = array(
        "message" => array(
          "content" => $content,
          "content_type" => "default_card",
          "uuid" => Utils::guidv4(),
          "conversation" => array("uuid" => $conversation_uuid),
          "user" => array("layer_id" => $this->bot_uuid),
          "attachments" => array(
            "default_card" => array()
          )
        )
      );
      if($card->showcase_image_path) {
        $post_data["message"]["attachments"]["default_card"]["showcase_image_uuid"] = $card->showcase_image_uuid;
      }
      if($card->title) {
        $post_data["message"]["attachments"]["default_card"]["title"] = $card->title;
      }
      if($card->description) {
        $post_data["message"]["attachments"]["default_card"]["description"] = $card->description;
      }
      if($card->links && count($card->links) > 0) {
        $post_data["message"]["attachments"]["default_card"]["links"] = array();
        foreach ($card->links as $key => $value) {
          array_push($post_data["message"]["attachments"]["default_card"]["links"], array(
            "position" => (int)$value->position,
            "title" => $value->title,
            "type" => $value->type,
            "url" => $value->url
          ));
        }
      }

      $this->post(json_encode($post_data), false, "application/json");
      $this->logger->printLogs("Card posted");
    }
  }

  /**
   *
   */
  class Card {

    public $title = "";
    public $description = "";
    public $showcase_image_path = "";
    public $showcase_image_uuid = "";
    public $links = array();

    function __construct($card = array()) {
      if($card["title"] == "" && $card["description"] == "" && $card["showcase_image_path"] == "" && count($card["links"]) <= 0) {
        throw new Exception("At least one of the parameter must be non empty");
      }
      $this->title = $card["title"];
      $this->description = $card["description"];
      $this->showcase_image_uuid = $card["showcase_image_uuid"];
      $this->showcase_image_path = $card["showcase_image_path"];
      foreach ($card["links"] as $key => $value) {
        $value->position = $key;
        array_push($this->links, $value);
      }
    }
  }

?>
