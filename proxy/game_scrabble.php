<?php

// GAME HANDLER - EMAIL SCRABBLE FOOTBALL
// Header info is mostly 0
//  getting more info would require picking apart the .SEM file

function b_search($array, $value)
{
    $l = 0;
    $r = count($array) - 1;

    while ($l <= $r) {
      // Set the initial midpoint to the rounded down value of half the length of the array.
      $m = intdiv($l + $r, 2);

      $cmp = strcmp($array[$m], $value);

      if ($cmp < 0) {
        $l = $m + 1;
      } elseif ($cmp > 0) {
        // The midpoint value is greater than the value.
        $r = $m - 1;
      } else {
        // This is the key we are looking for.
        return true;
      }
    }
    // The value was not found.
    return false;
}

function handle_scrabble($timestamp, $header, $custom, $payload, $players)
{
  _d("-- SCRABBLE FILE --");
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

  // expect that most values for Padding are 0
  check('padding_count', 0x10, count($custom));
  foreach ($custom as $i => $padding) {
    check('padding[' . $i . ']', 0x0, $padding);
  }

  //  unique identifier for game
  //  just timestamp is good enough for now
  $id = $game_start;

  // SEM file size and content
  //  NOTE: there are TWO paths here depending on your action ($header[1])
  if ($header[1] == 0x09) {
    _d("Dictionary lookup");
    // This is a word lookup request
    //  Each of the 9 slots of the header block contain a 16-bytes string
    $dict = file('./CSW21.txt');
    // Check each one against the dictionary.
    //  If any are bad, set the response flag.
    $flag = 0;
    for ($i = 0; $i < 9; $i ++) {
      $chunk = substr($payload, 0x10 * $i, 0x10);
      $word = unpack("Z*", $chunk)[1];
      _d("Word $i: '$word'");
      if ($word && ! b_search($dict, $word)) {
        // word not found!  set flags depending on value of $i
        if ($i < 4) {
          // first 4 words can be individually reported
          $flag |= (0xFF000000 >> ($i * 2));
        } else {
          // any word higher than this cannot, flag everything
          $flag = 0xFFFFFFFF;
          break;
        }
      }
    }

    return array( $id, '', '', pack("N", $flag) . str_repeat(chr(0), 0x3C) );
  } else {
    _d("Email send request");
    //  TODO: this appears to be Deflate-compressed somehow
    //  parsing it is not needed right now

    // we wish to know who started the game
    //  since this is only 2 player we can use even-odd turn number
    $player_index_0 = $turn_number % 2;
    $player_index_1 = 1 - $player_index_0;

    // let's build some response info
    $message = sprintf(
      "%s\nGame: %s vs. %s\nTurn number: %d\n(Game ID: `%d`)\n",
      ($game_accepted ? "🔠 **IT'S YOUR TURN**" : "❓ **GAME REQUESTED!**"),
      $players[$player_index_0]['name'], $players[$player_index_1]['name'],
      $turn_number,
      $game_start);

    $filename = sprintf("scrabble_%010d_t%02d_%s_vs_%s.sem",
      $game_start,
      $turn_number,
      $players[$player_index_0]['name'],
      $players[$player_index_1]['name']);

    // return info about the game that we want sent in the message
    return array( $id, $message, $filename, str_repeat(chr(0), 0x40) );
  }
}

// register the handler in the games array
$games[ 0x00003211 ] = 'handle_scrabble';

?>
