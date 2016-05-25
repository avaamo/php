<?php
/**
  *   fileName: avaamo.php
  *   createdAt: May 6, 2016
  *   Author: Jebin
  *   Description: Class to initialize and communicate to avaamo server
 */

define(HOST, "https://ds.avaamo.com");
define(APP_SERVER_HOST, "https://prod.avaamo.com/s");

require 'utils.php';
require 'attachment.php';

class Avaamo {

  private $ref = 1;
  protected $logger = false;
  private $bot_uuid = "";
  private $access_token = "";
  private $DS_HOST = HOST;

  private static $HAS_CONTENT = 200;
  private static $NO_CONTENT = 204;
  private static $NEW_TOKEN = 410;

  private static $STATUS_SUCCESS = 200;

  private $CHANNELS = array();
  private $messages_channel = "";

  private static $EVENT_JOIN = "phx_join";
  private static $EVENT_MESSAGE = "message";
  private static $EVENT_REPLY = "phx_reply";

  public function __construct($bot_uuid, $access_token, $onMessageCallback, $onReadAckCallback, $logger = false) {
    $this->logger = new Logger($logger);
    $this->bot_uuid = $bot_uuid;
    $this->access_token = $access_token;
    $this->onMessageCallback = $onMessageCallback;
    $this->onReadAckCallback = $onReadAckCallback;

    $this->DS_HOST = HOST;

    $this->postRequestObject = new Attachment($bot_uuid, $access_token, $logger);
    $this->imageAttachment = new ImageAttachment($bot_uuid, $access_token, $logger);
    $this->fileAttachment = new FileAttachment($bot_uuid, $access_token, $logger);
    $this->cardAttachment = new CardAttachment($bot_uuid, $access_token, $logger);

    if(!$bot_uuid) {
      throw "Bot UUID is not passed";
    }
    if(!$access_token) {
      throw "Access Token is not passed";
    }
    $this->messages_channel = "messages.".$bot_uuid;
    array_push($this->CHANNELS, $this->messages_channel);
    $this->init();
  }

  private function init() {
    $this->setURL();
    if($this->authenticate() == true) {
      $this->joinChannels();
    }
  }

  private function authenticate() {
    $response = $this->fireRequestAndGetResponse();
    if($response->status == 410) {
      $this->setURL($response->token);
      $this->logger->printLogs("Authentication successful");
      return true;
    } else {
      $this->logger->printLogs("Authentication FAILED");
      return false;
    }
  }

  private function joinChannels() {
    $channelCount = count($this->CHANNELS);
    foreach ($this->CHANNELS as $key => $value) {
      $request = new stdClass();
      $request->topic = $value;
      $request->event = self::$EVENT_JOIN;
      $request->payload = new stdClass();
      $request->ref = (string)$this->ref++;

      $respose = $this->fireRequestAndGetResponse($this->getContext("POST", json_encode($request)));
      $this->logger->printLogs("Status should be 200 && it is ".$respose->status);
      if($respose->status == self::$STATUS_SUCCESS) {
        $this->logger->printLogs("Join channel $value requested");
        if($key == ($channelCount-1)) {
          $this->poll();
        }
      } else {
        $this->logger->printLogs("Join channel $value request failed");
      }
    }
  }

  private function poll() {
    $this->logger->printLogs("Poll started");
    do {
      $respose = $this->fireRequestAndGetResponse();
      $this->logger->printLogs($respose);
      if(($respose->status == self::$HAS_CONTENT) && count($respose->messages) > 0) {
        foreach ($respose->messages as $value) {
          if($value->event == self::$EVENT_REPLY) {
            //TODO: Joined state identification and message sent identification
            $this->logger->printLogs("Reply from channel $value->topic\n".json_encode($value));
          } elseif ($value->event == self::$EVENT_MESSAGE && $value->topic == $this->messages_channel) {
            $this->logger->printLogs("Message received from channel $value->topic\n".json_encode($value));
            if($value->payload->pn_native) {
              call_user_func($this->onMessageCallback, $value->payload->pn_native, $this);
            } elseif ($value->payload->read_ack) {
              call_user_func($this->onReadAckCallback, $value->payload, $this);
            }
          }
        }
        $this->setURL($respose->token);
      } elseif($respose->status == self::$NEW_TOKEN) {
        $this->setURL($respose->token);
      } elseif($respose->status == self::$NO_CONTENT) {
        $this->setURL($respose->token);
      } elseif($respose->status == 0 || $respose->status == 500) {
        $this->logger->printLogs("Error while receiving data");
        break;
      }
    } while (1);
  }

  public function sendMessage($msg, $conversation_uuid) {
    $post_data = array(
      "[message][conversation][uuid]" => $conversation_uuid,
      "[message][user][layer_id]" => $this->bot_uuid,
      "[message][content]" => $msg,
      "[message][content_type]" => "text",
      "[message][uuid]" => Utils::guidv4(),
      "[message][created_at]" => microtime(true)
    );
    $this->postRequestObject->post($post_data);

    /* via socket
    $content = new stdClass();
    $content->event = "message";
    $content->ref = (string)$this->ref++;
    $content->topic = $topic;
    $content->payload = new stdClass();
    $content->payload->message = new stdClass();
    $content->payload->message->content = $msg;
    $content->payload->message->content_type = "text";
    $content->payload->message->uuid = Utils::guidv4();
    $content->payload->message->created_at = microtime(true);
    $content->payload->message->user = new stdClass();
    $content->payload->message->user->layer_id = $this->bot_uuid;
    $content->payload->message->conversation = new stdClass();
    $content->payload->message->conversation->uuid = $conversation_uuid;
    $content->payload->header = new stdClass();
    $content->payload->header->access_token = $this->access_token;

    $response = $this->fireRequestAndGetResponse($this->getContext("POST", json_encode($content)));
    if($response->status == 200) {
      $this->logger->printLogs("Message sent successfully");
      return true;
    } else {
      return false;
    }*/
  }

  public function sendImage($path, $caption = "", $conversation_uuid) {
    $this->imageAttachment->send($path, $caption, $conversation_uuid);
  }

  public function sendFile($path, $conversation_uuid) {
    $this->fileAttachment->send($path, $conversation_uuid);
  }

  public function sendCard($card, $content = "", $conversation_uuid) {
    $this->cardAttachment->send(new Card($card), $content, $conversation_uuid);
  }

  private function setURL($token = null) {
    $url = "$this->DS_HOST/socket/longpoll?access_token=$this->access_token"."&vsn=1.0.0";
    if($token) {
      $url .= ("&token=".urlencode($token));
    }
    $this->url = $url;
  }

  private function fireRequestAndGetResponse($context = null) {
    $context == null ? $this->getContext() : $context;
    return json_decode(file_get_contents($this->url, false, $context));
  }

  private function getContext($method = "GET", $content = null) {
    $opts = array('http' =>
        array(
          'method'  => $method
        )
    );
    if($content) {
      $opts["http"]["content"] = $content;
    }
    return stream_context_create($opts);
  }
}

/**
 *
 */
class Link {
  public static $LINK_TYPE_WEBPAGE = "web_page";
  public static $LINK_TYPE_DEEPLINk = "deeplink";

  public $position = 0;
  public $type = "";
  public $title = "";
  public $url = "";

  function __construct($link = array("position" => 0, "type" => "web_page", "title" => "", "url" => "")) {
    $this->position = $link["position"];
    $this->type = $link["type"];
    $this->title = $link["title"];
    $this->url = $link["url"];
  }

  public static function getWebpageLink($title, $url) {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    if(!$url) {
      throw new Exception("URL cannot be empty");
    }
    $link = new self();
    $link->type = Link::$LINK_TYPE_WEBPAGE;
    $link->title = $title;
    $link->url = $url;
    return $link;
  }

  private static function getDeeplinkObject($title, $url) {
    $link = new self();
    $link->type = Link::$LINK_TYPE_DEEPLINk;
    $link->title = $title;
    $link->url = $url;
    return $link;
  }

  public static function get_auto_send_message_link($title, $message, $conversation_uuid = "") {
    if(!$message) {
      throw new Exception("Message cannot be empty");
    }
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }

    $message = rawurlencode($message);
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#messages/new/$message");
  }

  public static function get_open_conversation_link($title, $conversation_uuid) {
    if(!$conversation_uuid) {
      throw new Exception("Conversation uuid cannot be empty");
    }
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#conversations/$conversation_uuid");
  }

  public static function get_new_conversation_link($title, $user_uuid) {
    if(!$user_uuid) {
      throw new Exception("User uuid cannot be empty");
    }
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#create_conversation/$user_uuid");
  }

  // Open File sharing screen
  public static function get_file_sharing_screen_link($title) {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#share/attachment/file");
  }

  // Open Image sharing screen
  public static function get_image_sharing_screen_link($title) {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#share/attachment/image");
  }

  // Open Video sharing screen
  public static function get_video_sharing_screen_link($title) {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#share/attachment/video");
  }

  // Open Gallery
  public static function get_gallery_open_link($title) {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#share/attachment/gallery");
  }

  // Open Contact sharing screen
  public static function get_contact_sharing_screen_link($title) {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#share/attachment/contact");
  }

  //go to forms
  public static function get_go_to_form_list_link($title) {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#share/attachment/form");
  }

  //go to specific form
  public static function get_go_to_forms_link($title, $form_uuid, $form_name, $conversation_uuid = "{self}") {
    if(!$title) {
      throw new Exception("Title cannot be empty");
    }
    if(!$form_uuid) {
      throw new Exception("Form uuid cannot be empty");
    }
    if(!$form_name) {
      throw new Exception("Form name cannot be empty");
    }
    $conversation_uuid = !$conversation_uuid ? "{self}" : $conversation_uuid;
    return Link::getDeeplinkObject($title, "https://web.avaamo.com#forms/$form_uuid?form_name=$form_name&conversation_uuid=$conversation_uuid");
  }
}

?>
