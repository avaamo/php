<?php
/**
  *   fileName: avaamo.php
  *   createdAt: May 6, 2016
  *   Author: Jebin
  *   Description: Class to initialize and communicate to avaamo server
 */

require 'utils.php';
require 'attachment.php';
require 'message.php';

class Avaamo {

  private $ref = 1;
  protected $logger = false;
  private $bot_uuid = "";
  private $access_token = "";

  private static $HAS_CONTENT = 200;
  private static $NO_CONTENT = 204;
  private static $NEW_TOKEN = 410;

  private static $STATUS_SUCCESS = 200;

  private $CHANNELS = array();
  private $messages_channel = "";
  private $activity_channel = "";

  private static $EVENT_JOIN = "phx_join";
  private static $EVENT_MESSAGE = "message";
  private static $EVENT_REPLY = "phx_reply";

  public static $MESSAGE_CONTENT_TYPE_TEXT = "text";
  public static $MESSAGE_CONTENT_TYPE_RICHTEXT = "richtext";
  public static $MESSAGE_CONTENT_TYPE_FILE = "file";
  public static $MESSAGE_CONTENT_TYPE_VIDEO = "video";
  public static $MESSAGE_CONTENT_TYPE_AUDIO = "audio";
  public static $MESSAGE_CONTENT_TYPE_PHOTO = "photo";
  public static $MESSAGE_CONTENT_TYPE_IMAGE = "image";
  public static $MESSAGE_CONTENT_TYPE_LINK = "link";
  public static $MESSAGE_CONTENT_TYPE_FORM_RESPONSE = "form_response";
  public static $MESSAGE_CONTENT_TYPE_DEFAULT_CARD = "default_card";
  public static $MESSAGE_CONTENT_TYPE_SMART_CARD = "smart_card";

  public static $ACCESS_TOKEN = null;
  public static $BOT_UUID = null;
  public static $DEBUG = false;

  public function __construct($bot_uuid, $access_token, $onMessageCallback, $onReadAckCallback, $onActivityCallback, $logger = false) {
    if(!$bot_uuid) {
      throw new Exception("Bot UUID is not passed");
    }
    if(!$access_token) {
      throw new Exception("Access Token is not passed");
    }

    API::init();

    self::$DEBUG = $logger;
    self::$BOT_UUID = $bot_uuid;
    self::$ACCESS_TOKEN = $access_token;

    $this->logger = new Logger();

    $this->onMessageCallback = $onMessageCallback;
    $this->onReadAckCallback = $onReadAckCallback;
    $this->onActivityCallback = $onActivityCallback;

    $this->imageAttachment = new ImageAttachment();
    $this->fileAttachment = new FileAttachment();
    $this->cardAttachment = new CardAttachment();

    $this->messages_channel = "messages.".$bot_uuid;
    $this->activity_channel = "activity.".$bot_uuid;
    array_push($this->CHANNELS, $this->messages_channel, $this->activity_channel);
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
      Logger::printLogs("Authentication successful");
      return true;
    } else {
      Logger::printLogs("Authentication FAILED");
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
      Logger::printLogs("Status should be 200 && it is ".$respose->status);
      if($respose->status == self::$STATUS_SUCCESS) {
        Logger::printLogs("Join channel $key requested");
        if($key == ($channelCount-1)) {
          echo "Bot listening...\n";
          $this->poll();
        }
      } else {
        Logger::printLogs("Join channel $key request failed");
      }
    }
  }

  private function poll() {
    Logger::printLogs("Poll started");
    do {
      $respose = $this->fireRequestAndGetResponse();
      Logger::printLogs("POLL_STATUS: ".$respose->status." || MESSAGE_COUNT: ".count($respose->messages));
      if(($respose->status == self::$HAS_CONTENT) && count($respose->messages) > 0) {
        foreach ($respose->messages as $value) {
          if($value->event == self::$EVENT_REPLY) {
            //TODO: Joined state identification and message sent identification
            Logger::printLogs("Reply from channel ".$value->payload->status);
          } elseif ($value->event == self::$EVENT_MESSAGE && $value->topic == $this->messages_channel) {
            Logger::printLogs("Message received from Messages channel");
            if($value->payload->pn_native) {
              $payload = $value->payload->pn_native;
              $payload->message = new Message($payload);
              $this->acknowledgeMessage($payload->message);
              call_user_func($this->onMessageCallback, $payload, $this);
            } elseif ($value->payload->read_ack) {
              call_user_func($this->onReadAckCallback, $value->payload, $this);
            }
          } elseif($value->event == self::$EVENT_MESSAGE && $value->topic == $this->activity_channel) {
            Logger::printLogs("Message received from Activity channel");
            call_user_func($this->onActivityCallback, $value->payload->activity, $this);
          }
        }
        $this->setURL($respose->token);
      } elseif($respose->status == self::$NEW_TOKEN) {
        $this->setURL($respose->token);
      } elseif($respose->status == self::$NO_CONTENT) {
        $this->setURL($respose->token);
      } elseif($respose->status == 0 || $respose->status == 500) {
        Logger::printLogs("Error while receiving data");
        break;
      }
    } while (1);
  }

  public function sendMessage($msg, $conversation_uuid) {
    Logger::printLogs("\n==> Sending text message...");
    $post_data = array(
      "message" => array(
        "content" => $msg,
        "content_type" => Avaamo::$MESSAGE_CONTENT_TYPE_TEXT,
        "uuid" => Utils::guidv4(),
        "created_at" => microtime(true),
        "conversation" => array("uuid" => $conversation_uuid),
        "user" => array('layer_id' => self::$BOT_UUID)
      )
    );
    $response = Utils::POST(json_encode($post_data), null, "application/json");
    Logger::printLogs("==> Message sending done!\n");
    return $response;
  }

  private function acknowledgeMessage($message) {
    Logger::printLogs("\n==> Acknowledging the received message...");
    $post_data = array(
      "read_acks" => array(
        array(
          "read_at" => microtime(true),
          "message" => array(
            "conversation_uuid" => $message->getConversationUuid(),
            "uuid" => $message->getUuid(),
            "user" => array("layer_id" => $message->getSender()->layer_id)
          )
        )
      )
    );
    $response = Utils::POST(json_encode($post_data), API::$APP_SERVER_READ_ACK, "application/json");
    Logger::printLogs("==> Acknowledgement processed!\n");
    return $response;
  }

  public function sendImage($path, $caption = "", $conversation_uuid) {
    Logger::printLogs("\n==> Sending image...");
    $this->imageAttachment->send($path, $caption, $conversation_uuid);
    Logger::printLogs("==> Image sending done!\n");
  }

  public function sendFile($path, $conversation_uuid) {
    Logger::printLogs("\n==> Sending File...");
    $this->fileAttachment->send($path, $conversation_uuid);
    Logger::printLogs("==> File sending done!\n");
  }

  public function sendCard($card, $content = "", $conversation_uuid) {
    Logger::printLogs("\n==> Sending card...");
    $this->cardAttachment->send(new Card($card), $content, $conversation_uuid);
    Logger::printLogs("==> Card sending done!\n");
  }

  private function setURL($token = null) {
    $url = API::$DS_SERVER_HOST."/socket/longpoll?access_token=".self::$ACCESS_TOKEN."&vsn=1.0.0";
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
