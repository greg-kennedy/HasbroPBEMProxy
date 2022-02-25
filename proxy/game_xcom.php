<?php

// GAME HANDLER - EMAIL X-COM
// Header info is mostly 0
//  getting more info would require picking apart the .XEM file

function handle_xcom($timestamp, $header, $custom, $payload, $players)
{
  // the next 9 U32 in the Header are various indicators and flags
  check('unknown1', 1, $header[0]);
  $game_accepted = $header[1];
  $turn_number = $header[2];
  $game_start = $header[3];
  check('coffee', 0x00EEFF0C, $header[4]);
  check('unknown2', 1, $header[5]);
  check('unknown3', 0, $header[6]);

  $checksum1 = $header[7]; // is this a timestamp?
  $checksum2 = $header[8];

  // expect that all X-COM values for Padding are 0
  check('padding_count', 0x10, count($custom));
  foreach ($custom as $i => $padding) {
    check('padding[' . $i . ']', 0x0, $padding);
  }

  // XEM file size and content
  //  TODO: don't know how to handle the Payload, not much we can do anyway

  // we wish to know who started the game
  //  since this is only 2 player we can use even-odd turn number
  $player_index_0 = $turn_number % 2;
  $player_index_1 = 1 - $player_index_0;

  // let's build some response info
  //  unique identifier for game
  //  just timestamp is good enough for now
  $id = $game_start;

  $message = sprintf(
    "%s\nGame: %s vs. %s\nTurn number: %d\n(Game start: <t:%d>)\n",
    ($game_accepted ? "💥 **IT'S YOUR TURN**" : "❓ **GAME REQUESTED!**"),
    $players[$player_index_0]['name'], $players[$player_index_1]['name'],
    $turn_number,
    $game_start);

  $filename = sprintf("xcom_%010d_t%02d_%s_vs_%s.xem",
    $game_start,
    $turn_number,
    $players[$player_index_0]['name'],
    $players[$player_index_1]['name']);


  // return info about the game that we want sent in the message
  return array( $id, $message, $filename );
}

// register the handler in the games array
$games[ 0x00003939 ] = 'handle_xcom';

?>
