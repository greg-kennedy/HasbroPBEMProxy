<?php

// GAME HANDLER - EMAIL NASCAR
// Header info has some mix of unknown value
//  Also, the NCE file is pretty easy to parse

function handle_nascar($timestamp, $header, $custom, $payload, $players)
{
  _d("-- NASCAR FILE --");
  foreach($header as $i => $val) {
    _d( sprintf("header[%d] => %d (%08x)", $i, $val, $val) );
  }
  // the next 9 U32 in the Header are various indicators and flags
  check('unknown1', 1, $header[0]);
  $num_players = $header[1];
  $turn_number = $header[2];
  check('unknown2', 1, $header[3]);
  check('coffee', 0x00EEFF0C, $header[4]);
  $unknown3 = $header[5];
  $unknown4 = $header[6];

  $checksum1 = $header[7]; // is this a timestamp?
  $checksum2 = $header[8];

  foreach($custom as $i => $val) {
    _d( sprintf("custom[%d] => %d (%08x)", $i, $val, $val) );
  }

  // NCE file size and content
  //  We don't have a good "turn start" so instead, for ID,
  //  we have to use concat of first user name + all emails
  $player_names = array();
  $player_emails = array();
  $email_count = 0;
  for ($i = 0; $i < 6; $i ++)
  {
    $player_names[$i] = (unpack("Z*", $payload, 0x2F + ($i * 0x6A)))[1];
    $player_emails[$i] = (unpack("Z*", $payload, 0x3A + ($i * 0x6A)))[1];
    if ($player_emails[$i] != "") {
      $email_count ++;
    }
  }

  // let's build some response info
  //  unique identifier for game
  $id = $player_names[0] . implode('', $player_emails);

  if ($num_players == 0) {
    $message = "❓ **GAME REQUESTED!**\n";
  } else if ($turn_number == 0) {
    $message = "⁉️ **MORE PLAYERS NEEDED!**\n";
  } else {
    $message = "🏁 **IT'S YOUR TURN!**\n";
  }

  $message .= "Turn: $turn_number\n$email_count players\n";
  for ($i = 0; $i < 6; $i ++) {
    if ($player_emails[$i] === "") {
      $emoji = "🤖";
    } else if ($turn_number == 0 && ($player_names[$i] === "Player " . $i+1)) {
      $emoji = "👻";
    } else {
      $emoji = "🏎️";
    }
    $message .= $emoji . " " . $player_names[$i] . "\n";
  }

  $filename = sprintf("nascar_%s_race_t%02d_%011d.nce",
    $player_names[0], $turn_number, $timestamp);

  // return info about the game that we want sent in the message
  return array( $id, $message, $filename, str_repeat(chr(0), 0x40) );
}

// register the handler in the games array
$games[ 0x0000323e ] = 'handle_nascar';

?>
