<?php
class Message {
  public $content = "";
  public $content_type = "text";
  public $attachments = null;
  public $conversation_uuid = null;
  public $uuid = "";
  public $created_at = "";
  private $user = null;

  public function __construct($msg) {
    $this->logger = new Logger();

    $this->content = $msg->message->content;
    $this->content_type = $msg->message->content_type;
    $this->uuid = $msg->message->uuid;
    $this->created_at = $msg->message->created_at;
    $this->conversation_uuid = $msg->message->conversation_uuid;
    $this->user = $msg->user;
    $this->attachments = $msg->message->attachments;
    if($this->hasAttachment()) {
      $attachmentObj = new Attachment($msg->message->attachments, $this->content_type);
      $this->attachments = $attachmentObj->getAttachments();
    }
  }

  public function getUuid() {
    return $this->uuid;
  }

  public function getConversationUuid() {
    return $this->conversation_uuid;
  }

  public function getCreatedAt() {
    return $this->created_at;
  }

  public function getContent() {
    return $this->content;
  }

  public function getContentType() {
    return $this->content_type;
  }

  public function getSender() {
    return $this->user;
  }

  public function getSenderName($default_name = "") {
    $name = $default_name;
    if($this->user) {
      $name = $this->user->first_name." ".$this->user->last_name;
    }
    return $name;
  }

  public function hasAttachment() {
    return $this->attachments && is_object($this->attachments) && count((array) $this->attachments) > 0;
  }

  /*
    Returns one of the following values if message has attachment:
      video, audio, image, photo, form_response, default_card, link, smart_card
    Returns null if message has no valid attachment
  */
  public function whichAttachment() {
    if($this->hasAttachment()) {
      return $this->content_type;
    } else {
      return null;
    }
  }

  public function hasImage() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_IMAGE;
  }

  public function hasFile() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_FILE;
  }

  public function hasPhoto() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_PHOTO;
  }

  public function hasAudio() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_AUDIO;
  }

  public function hasVideo() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_VIDEO;
  }

  public function hasDefaultCard() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_DEFAULT_CARD;
  }

  public function hasSmartCard() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_SMART_CARD;
  }

  public function hasLink() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_LINK;
  }

  public function isRichText() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_RICHTEXT;
  }

  public function isText() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_TEXT;
  }

  public function hasForm() {
    return $this->content_type === Avaamo::$MESSAGE_CONTENT_TYPE_FORM_RESPONSE;
  }
}
?>
