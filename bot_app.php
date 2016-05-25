<?php
  require("lib/avaamo.php");
  function printMessage($msg, $avaamo) {
    //message is received here
    //Do any processing here
    $name = "user";
    if($msg->user) {
      $name = $msg->user->first_name." ".$msg->user->last_name;
    }
    echo "\n==> ".$name.": ". $msg->message->content."\n";

    switch (strtoupper($msg->message->content)) {
      case "HI":
        $avaamo->sendMessage("Hello $name", $msg->conversation->uuid);
        break;
      case "IMAGE":
        $avaamo->sendImage("assets/superman.jpg", "I am Clark Kent. I have another name - Kal. I am the SUPERMAN.", $msg->conversation->uuid);
        break;
      case "FILE":
        $avaamo->sendFile("assets/relativity.pdf", $msg->conversation->uuid);
        break;
      case "CARD":
        $card = array(
          "title" => "Card Title",
          "description" => "Card Description. This has minimal rich text capabilities as well. For example <b>Bold</b> <i>Italics</i>",
          "showcase_image_path" => "assets/welcome.jpg",
          "links" => array(
            Link::get_auto_send_message_link("Post a Message", "Sample Action"),
            Link::getWebpageLink("Web URL", "http://www.avaamo.com"),
            Link::get_go_to_forms_link("Open a Form", "63c906c3-553e-9680-c273-28d1e54da050", "Say Yes")
          )
        );
        $avaamo->sendCard($card, "This is a sample card with rich text description, web link and deep links", $msg->conversation->uuid);
        break;
      case "SAMPLE ACTION":
        $avaamo->sendMessage("Lopadotemachoselachogaleokranioleipsanodrimhypotrimmatosilphioparaomelitokatakechymenokichlepikossyphophattoperisteralektryonoptekephalliokigklopeleiolagoiosiraiobaphetraganopterygon", $msg->conversation->uuid);
        $avaamo->sendMessage("No. I am not scolding you in my language. This is longest word ever to appear in literature.", $msg->conversation->uuid);
        break;
      default:
        $avaamo->sendMessage("Awesome. It works!. \nType one of the following to see them in action. \nimage \nfile \ncard", $msg->conversation->uuid);
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

  function myBot() {

    $bot_uuid = "09099b3b-8b9e-42fd-99c5-8c13cc3fbfc8";
    $access_token = "BTPUkyFh2EqKKNJyn-bpwzIvr4YQD0hd";

    //BOT UUID goes here
    // $bot_uuid = "<bot-uuid>";

    //BOT access token goes here
    // $access_token = "<bot-access-token>";

    new Avaamo($bot_uuid, $access_token, 'printMessage', 'printAck');
  }

  myBot();
?>
