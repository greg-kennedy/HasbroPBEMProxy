<?php

// GAME HANDLER - EMAIL NFL FOOTBALL
// Header info is mostly 0
//  getting more info would require picking apart the .EFB file

function handle_nfl($timestamp, $header, $custom, $payload, $players)
{
  _d("-- NFL FOOTBALL FILE --");
  foreach($header as $i => $val) {
    _d( sprintf("header[%d] => %d (%08x)", $i, $val, $val) );
  }

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

  foreach($custom as $i => $val) {
    _d( sprintf("custom[%d] => %d (%08x)", $i, $val, $val) );
  }

  // expect that all X-COM values for Padding are 0
  check('padding_count', 0x10, count($custom));
  foreach ($custom as $i => $padding) {
    check('padding[' . $i . ']', 0x0, $padding);
  }

  // EFB file size and content
  //  TODO: this appears to be Deflate-compressed somehow
  //  parsing it is not needed right now

  // we wish to know who started the game
  //  since this is only 2 player we can use even-odd turn number
  $player_index_0 = $turn_number % 2;
  $player_index_1 = 1 - $player_index_0;

  // let's build some response info
  //  unique identifier for game
  //  just timestamp is good enough for now
  $id = $game_start;

  $message = sprintf(
    "%s\nGame: %s vs. %s\nTurn number: %d\n(Game ID: `%d`)\n",
    ($game_accepted ? "🏈 **IT'S YOUR TURN**" : "❓ **GAME REQUESTED!**"),
    $players[$player_index_0]['name'], $players[$player_index_1]['name'],
    $turn_number,
    $game_start);

  $filename = sprintf("nfl_%010d_t%02d_%s_vs_%s.efb",
    $game_start,
    $turn_number,
    $players[$player_index_0]['name'],
    $players[$player_index_1]['name']);


  // return info about the game that we want sent in the message
  return array( $id, $message, $filename );
}

// register the handler in the games array
$games[ 0x0000321b ] = 'handle_nfl';

?>
