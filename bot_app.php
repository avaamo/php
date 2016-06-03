<?php
  require("lib/avaamo.php");
  function printMessage($payload, $avaamo) {
    //message is received here
    //Do any processing here

    $name = $payload->message->getSenderName("User");
    $path = "./assets/downloads";
    $content = $payload->message->hasAttachment() ? $payload->message->whichAttachment() : $payload->message->getContent();
    $conversation_uuid = $payload->message->getConversationUuid();
    echo "\n==> ".$name.": ". $content."\n";

    switch (strtoupper($payload->message->getContent())) {
      case "HI":
        $avaamo->sendMessage("Hello $name", $conversation_uuid);
        break;
      case "IMAGE":
        $avaamo->sendImage("assets/superman.jpg", "I am Clark Kent. I have another name - Kal. I am the SUPERMAN.", $conversation_uuid);
        break;
      case "FILE":
        $avaamo->sendFile("assets/relativity.pdf", $conversation_uuid);
        break;
      case "CARD":
        $card = array(
          "title" => "Card Title",
          "description" => "Card Description. This has minimal rich text capabilities as well. For example <b>Bold</b> <i>Italics</i>",
          "showcase_image_path" => "assets/welcome.jpg",
          "links" => array(
            Link::get_auto_send_message_link("Post a Message", "Sample Action"),
            Link::getWebpageLink("Web URL", "http://www.avaamo.com"),
            Link::get_go_to_forms_link("Open a Form", "8e893b85-f206-4156-ae49-e917d584bcf3", "Rate Me")
          )
        );
        $avaamo->sendCard($card, "This is a sample card with rich text description, web link and deep links", $conversation_uuid);
        break;
      case "SAMPLE ACTION":
        $avaamo->sendMessage("Lopadotemachoselachogaleokranioleipsanodrimhypotrimmatosilphioparaomelitokatakechymenokichlepikossyphophattoperisteralektryonoptekephalliokigklopeleiolagoiosiraiobaphetraganopterygon", $conversation_uuid);
        $avaamo->sendMessage("No. I am not scolding you in my language. This is longest word ever to appear in literature.", $conversation_uuid);
        break;
      default:
        if($payload->message->hasAttachment()) {
          $payload->message->attachments->downloadAll($path);
          $avaamo->sendMessage("I have got ".$payload->message->whichAttachment()." attachment", $conversation_uuid);
          if($payload->message->hasForm()) {
            echo "\n==> Form attachment!\n";
            $replies = "";
            foreach($payload->message->attachments->getFormResponse() as $response) {
              $answer = $response->getResponse();
              if(is_array($answer)) {
                $answer = implode(", ", $answer);
              }
              $replies .= $response->question->title." :: ".$answer."\n";
            }
            echo $replies;
            echo "\n==> Form attachment ends!";
            $avaamo->sendMessage("Confirm your replies: \n".$replies, $conversation_uuid);
          }
        } else {
          $avaamo->sendMessage("Awesome. It works!. \nType one of the following to see them in action. \nimage \nfile \ncard", $conversation_uuid);
        }
        break;
    }
  }

  function printAck($ack, $avaamo) {
    //acks are printed here.
    //Do your processing with the ack right here.

    date_default_timezone_set('Asia/Kolkata');
    $name = "Nobody";
    if($ack->user) {
      $name = $ack->user->first_name." ".$ack->user->last_name;
    }
    $date = date("d/m/Y H:iA", $ack->read_ack->read_at);
    echo "\n===> Messae read by << $name >> at $date\n";
  }

  function printAcitivity($activity, $avaamo) {
    date_default_timezone_set('Asia/Kolkata');
    $name = "User";
    $event = null;
    if($activity->user) {
      $name = $activity->user->first_name." ".$activity->user->last_name;
    }
    if($activity->type === "user_visit") {
      $event = "visited";
    }
    $date = date("d/m/Y H:iA", $activity->created_at);
    echo "\n==> $name $event me at $date\n";
  }

  function myBot() {
    //BOT UUID goes here
    $bot_uuid = "";

    //BOT access token goes here
    $access_token = "";

    new Avaamo($bot_uuid, $access_token, 'printMessage', 'printAck', 'printAcitivity');
  }

  myBot();
?>
