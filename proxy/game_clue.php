<?php

// GAME HANDLER - EMAIL CLUE
// Header info has some mix of unknown value
//  Also, the LEM file is pretty easy to parse

function handle_clue($timestamp, $header, $custom, $payload, $players)
{
  _d("-- CLUE FILE --");
  foreach($header as $i => $val) {
    _d( sprintf("header[%d] => %d (%08x)", $i, $val, $val) );
  }
  // the next 9 U32 in the Header are various indicators and flags
  check('unknown1', 1, $header[0]);
  $num_players = $header[1];
  $turn_number = $header[2];
  //check('unknown2', 1, $header[3]);
  $id = $header[3];
  check('coffee', 0x00EEFF0C, $header[4]);
  $unknown3 = $header[5];
  $unknown4 = $header[6];

  $checksum1 = $header[7]; // is this a timestamp?
  $checksum2 = $header[8];

  foreach($custom as $i => $val) {
    _d( sprintf("custom[%d] => %d (%08x)", $i, $val, $val) );
  }

  // LEM file has a lot of junk but it is possible to parse names
  $player_names = array();
  $player_count = (unpack("C", $payload, 0x10))[1];
  _d("looks like $player_count players here");

  /*
  $offset = 0x13;
  for ($i = 0; $i < $player_count; $i ++)
  {
	  while ((unpack("n", $payload, $offset))[1] != 0x0E18) $offset ++;
    // there are 12 bytes of Unknown first
    $offset += 2;
    // now there is a string, 0x0A separated, of Name / Email / Message
    $text_block = (unpack("Z*", $payload, $offset))[1];
    _d("Text is $text_block");
    $offset += strlen($text_block);
    $text = explode( chr(0x0A), $text_block, 3);

    $player_names[$i] = $text[0];
    $offset += 12;
    //if ($text[0] !== "Player " . $i+1) {
//	    _d("Player's joined so +18 then");
 //     $offset += 18;
  //  }
    _d("Offset now $offset");
  }
   */
  // let's build some response info
  //  unique identifier for game
  //$id = $player_names[0] . implode('', $player_emails);

  if ($num_players == 0) {
    $message = "❓ **GAME REQUESTED!**\n";
  } else if ($turn_number + 1 < $num_players) {
    $message = "⁉️ **MORE PLAYERS NEEDED!**\n";
  } else {
    $message = "🔍 **IT'S YOUR TURN!**\n";
  }

  $message .= "Turn: $turn_number\n$player_count players\n";
  #for ($i = 0; $i < $player_count; $i ++) {
    #if ($turn_number == 0 && ($player_names[$i] === "Player " . $i+1)) {
      #$emoji = "👻";
    #} else {
      #$emoji = "🕵️";
    #}
    #$message .= $emoji . " " . $player_names[$i] . "\n";
  #}
  $message .= sprintf("Game ID `%08X`", $id);

  $filename = sprintf("clue_%08X_t%02d.lem",
    $id, $turn_number);

  // return info about the game that we want sent in the message
  return array( $id, $message, $filename );
}

// register the handler in the games array
$games[ 0x0000392a ] = 'handle_clue';

?>
