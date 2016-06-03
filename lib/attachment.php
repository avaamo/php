<?php
  /**
    *   fileName: attachment.php
    *   createdAt: May 24, 2016
    *   Author: Jebin
    *   Description: Classes to create attachment objects and send to conversation
   */

  class Attachment {
    private $attachments = null;
    public function __construct($attachment, $content_type) {
      $this->logger = new Logger();
      $content_type_attachment_class_map = array(
        Avaamo::$MESSAGE_CONTENT_TYPE_FILE => 'FileAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_AUDIO => 'FileAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_VIDEO => 'FileAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_PHOTO => 'ImageAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_IMAGE => 'ImageAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_FORM_RESPONSE => 'FormResponseAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_DEFAULT_CARD => 'DefaultCardAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_SMART_CARD => 'SmartCardAttachment',
        Avaamo::$MESSAGE_CONTENT_TYPE_LINK => 'LinkAttachment'
      );
      $content_type_attachment_key_map = array(
        Avaamo::$MESSAGE_CONTENT_TYPE_FILE => 'files',
        Avaamo::$MESSAGE_CONTENT_TYPE_AUDIO => 'files',
        Avaamo::$MESSAGE_CONTENT_TYPE_VIDEO => 'files',
        Avaamo::$MESSAGE_CONTENT_TYPE_PHOTO => 'files',
        Avaamo::$MESSAGE_CONTENT_TYPE_IMAGE => 'files',
        Avaamo::$MESSAGE_CONTENT_TYPE_FORM_RESPONSE => 'form_response',
        Avaamo::$MESSAGE_CONTENT_TYPE_DEFAULT_CARD => 'default_card',
        Avaamo::$MESSAGE_CONTENT_TYPE_SMART_CARD => 'smart_card',
        Avaamo::$MESSAGE_CONTENT_TYPE_LINK => 'link'
      );
      $attachment_class = $content_type_attachment_class_map[$content_type];
      $attachment_key = $attachment->{$content_type_attachment_key_map[$content_type]};
      Logger::printLogs("Attachment Class: $attachment_class");
      if(is_object($attachment_key)) {
        $this->attachments = new $attachment_class($attachment_key);
      } else {
        $attachments = array();
        foreach ($attachment_key as $attachment) {
          array_push($attachments, new $attachment_class($attachment));
        }
        $this->attachments = new Attachments($attachments);
      }
    }

    public function getAttachments() {
      return $this->attachments;
    }
  }

  /**
   *
   */
  class Attachments {

    function __construct($attachments) {
      $this->attachments = $attachments;
    }

    public function downloadAll($path, $permission = 0777) {
      foreach ($this->attachments as $attachment) {
        $attachment->download($path, $permission);
      }
    }

    public function getAll() {
      return $this->attachments;
    }
  }


  /**
   *
   */
  abstract class AttachmentHelper {

    public function __construct() {}

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
      Logger::printLogs("Path to fetch image: $encoded_path");
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
      $contentType = mime_content_type($path);

      Logger::printLogs("File details: Name: $fileName, Size: $fileSize, Content-Type: $contentType");
      return array("file_size" => $fileSize, "content_type" => $contentType, "file_name" => $fileName);
    }

    public function post($post_data, $url = false, $content_type = "multipart/form-data") {
      return Utils::POST($post_data, $url, $content_type);
    }

    protected function getFileName($path) {
      $path_explode = explode("/", $path);
      return $path_explode[count($path_explode) - 1];
    }
  }

  class FileAttachment extends AttachmentHelper {

    public $uuid = 0;
    public $type = null;
    public $size = 0;
    public $preview = false;
    public $name = null;
    public $meta = null;
    public $content_type = null;

    public function __construct($attachment = null) {
      parent::__construct($attachment);
      if($attachment) {
        $this->uuid = $attachment->uuid;
        $this->type = $attachment->type;
        $this->size = $attachment->size;
        $this->preview = $attachment->preview;
        $this->name = $attachment->name;
        $this->meta = $attachment->meta;
        $this->content_type = $attachment->content_type;
      }
    }

    public function download($path, $permission = 0777, $name = null) {
      Utils::download(API::getFile($this->uuid), $path, $permission, $this->name);
    }

    public function send($path, $conversation_uuid) {
      Logger::printLogs("Sending File...");
      $meta = $this->getFileMeta($path);
      $post_data = array(
        "[message][conversation][uuid]" => $conversation_uuid,
        "[message][user][layer_id]" => Avaamo::$BOT_UUID,
        "[message][attachments][files][][uid]" => Utils::guidv4(),
        "[message][attachments][files][][type]" => $meta["content_type"],
        "[message][attachments][files][][name]" => $meta["file_name"],
        "[message][attachments][files][][size]" => $meta["file_size"],
        "[message][attachments][files][][data]" => new CURLFile($path, $meta["content_type"], $meta["file_name"]),
        "[message][content]" => "",
        "[message][content_type]" => Avaamo::$MESSAGE_CONTENT_TYPE_FILE,
        "[message][uuid]" => Utils::guidv4()
      );
      $this->post($post_data);
      Logger::printLogs("File posted");
    }
  }

  class ImageAttachment extends FileAttachment {

    public function __construct($attachment = null) {
      parent::__construct($attachment);
    }

    public function send($path, $content = "", $conversation_uuid) {
      Logger::printLogs("Sending Image...");
      $meta = $this->getFileMeta($path);
      $post_data = array(
        "[message][conversation][uuid]" => $conversation_uuid,
        "[message][user][layer_id]" => Avaamo::$BOT_UUID,
        "[message][attachments][files][][uid]" => Utils::guidv4(),
        "[message][attachments][files][][type]" => $meta["content_type"],
        "[message][attachments][files][][name]" => $meta["file_name"],
        "[message][attachments][files][][size]" => $meta["file_size"],
        "[message][attachments][files][][data]" => new CURLFile($path, $meta["content_type"], $meta["file_name"]),
        "[message][content]" => $content,
        "[message][content_type]" => Avaamo::$MESSAGE_CONTENT_TYPE_PHOTO,
        "[message][uuid]" => Utils::guidv4(),
        "[message][created_at]" => microtime(true)
      );
      $this->post($post_data);
      Logger::printLogs("Image posted");
    }
  }

  class CardAttachment extends AttachmentHelper {

    public function __construct() {
      parent::__construct();
    }

    private function postImageAndGetID($path) {
      $meta = $this->getFileMeta($path);
      $post_data = array("data" => new CURLFile($path, $meta["content_type"], $meta["file_name"]));
      $response = json_decode($this->post($post_data, API::$APP_SERVER_FILE));
      return $response->file->uuid;
    }

    public function send($card, $content = "", $conversation_uuid) {
      $card->showcase_image_uuid = $this->postImageAndGetID($card->showcase_image_path);

      $post_data = array(
        "message" => array(
          "content" => $content,
          "content_type" => Avaamo::$MESSAGE_CONTENT_TYPE_DEFAULT_CARD,
          "uuid" => Utils::guidv4(),
          "conversation" => array("uuid" => $conversation_uuid),
          "user" => array("layer_id" => Avaamo::$BOT_UUID),
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
      Logger::printLogs("Card posted");
    }
  }

  /**
   *
   */
  class FormResponseAttachment extends AttachmentHelper {

    public $attachment = null;
    public $form = null;
    private $response = null;
    private $has_downloaded = false;

    function __construct($attachment) {
      $this->attachment = $attachment;
    }

    function getReplies() {
      return $this->getForm()->replies;
    }

    function getQuestions() {
      return $this->getForm()->questions;
    }

    function download($path, $permission = 0777) {
      $this->downloadAll($path, $permission);
    }

    function getForm() {
      if($this->has_downloaded !== true) {
        $url = API::getFormResponse($this->attachment->uuid);
        Logger::printLogs("Form Response fetching..");

        $this->response = json_decode(Utils::GET($url));
        Logger::printLogs("Form Response Received");

        $this->response->response->form->replies = $this->response->response->replies;
        $this->response->response->form->user = $this->response->response->user;
        $this->form = new FormResponse($this->response->response->form);
        $this->has_downloaded = true;
      }
      return $this->form;
    }

    function getQuestionsReplies() {
      $this->getForm();
      $form_response = array();
      foreach ($this->response->response->replies as $key => $value) {
        $obj_merged = (object) array_merge((array) $response->response->replies[$key], (array) $response->response->form->questions[$key]);
        array_push($form_response, $obj_merged);
      }
      Logger::printLogs("Final Merged Form Response", $form_response);
      return $form_response;
    }

    function getFormResponse() {
      Logger::printLogs("Getting form Response");
      return $this->getForm()->getAllResponses();
    }

    function downloadAll($path, $permission = 0777) {
      return $this->getForm()->downloadAllResponseAttachments($path, $permission);
    }

  }

  /**
   * corresponds to form property in the attachments object
   */
  class FormResponse {

    function __construct($form) {
      $this->form = $form;
      $this->setResponses();
    }

    private function setResponses() {
      foreach ($this->form->replies as $key => $value) {
        $this->form->replies[$key] = new Reply($value, $this->form->questions[$key]);
      }
    }

    function getTitle() {
      return $this->form->title;
    }

    function getDescription() {
      return $this->form->description;
    }

    function getUuid() {
      return $this->form->uuid;
    }

    function getReplies() {
      return $this->getAllResponses();
    }

    function getQuestions() {
      return $this->form->questions;
    }

    function getSender() {
      return $this->form->user;
    }

    function getAllResponses() {
      return $this->form->replies;
    }

    function downloadAllResponseAttachments($path, $permission = 0777) {
      foreach($this->form->replies as $key => $reply) {
        $this->form->replies[$key]->downloadAttachments($path, $permission = 0777);
      }
    }

    function getQuestionAtPosition($position) {
      if(array_key_exists($position, $this->form->replies)) {
        return $this->form->replies[$position]->getQuestion();
      } else {
        return null;
      }
    }

    function getReplyAtPosition($position) {
      if(array_key_exists($position, $this->form->replies)) {
        return $this->form->replies[$position]->getReply();
      } else {
        return null;
      }
    }

    function downloadAttachmentsAtPosition($position, $path, $permission = 0777) {
      if(array_key_exists($position, $this->form->replies)) {
        return $this->form->replies[$position]->downloadAttachments($path, $permission);
      } else {
        return null;
      }
    }

    function getResponseAtPosition($position) {
      if(array_key_exists($position, $this->form->replies)) {
        return $this->form->replies[$position]->getResponse();
      } else {
        return null;
      }
    }

  }

  /**
   *
   */
  class Reply {

    function __construct($response, $question) {
      $this->answer = $response->answer;
      $this->question_uuid = $response->question_uuid;
      $this->asset_info = $response->asset_info;
      $this->answerable = $response->answerable;
      $this->reply = $response->reply;
      $this->question = $question;
    }

    function hasAttachments() {
      return $this->answerable === true && $this->asset_info != null;
    }

    function downloadAttachments($path, $permission = 0777) {
      if($this->hasAttachments()) {
        foreach ($this->asset_info as $key => $value) {
          Utils::download(API::getFile($this->asset_info[$key]->asset_id), $path, $permission, $this->asset_info[$key]->file_name);
        }
        return true;
      } else {
        return false;
      }
    }

    function getQuestion() {
      return $this->question;
    }

    function getResponse() {
      return $this->answer;
    }

    function getReply() {
      return $this->reply;
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
