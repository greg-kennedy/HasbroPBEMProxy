<HTML><TITLE>title</TITLE><BODY><?php

// config
define('DEBUG', true);

//  these are the games we know about and the webhooks we can post to
$games = array();
require('game_xcom.php');
require('game_nascar.php');
require('game_nfl.php');
require('game_clue.php');

// use the Discord message post
require('message_discord.php');

//////////////////////////////////////////////////////////////////////////////
// enable a function to write to stderr if DEBUG enabled
function _d($message) {
  if (DEBUG) {
    // enable stderr writing
    if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'wb'));
    fwrite(STDERR, $message . "\n");
  }
}

// helper parsing function
function check($name, $expected, $actual)
{
  if ($expected !== $actual) {
    ///throw new Exception("Assertion failure: Value $name, expected $expected, got $actual");
    _d("Assertion failure: Value $name, expected $expected, got $actual");
  }
}

// read a UINT32 (network-byte-order) from a string + position
function readU32($stream, &$offset)
{
  $val = unpack("N", $stream, $offset);
  $offset += 4;
  return $val[1];
}

// read a block: size, then raw bytes of $size
function readSizedBlock($stream, &$offset)
{
  $size = readU32($stream, $offset);

  _d("Reading block of size $size");
  $data = substr($stream, $offset, $size);
  check('block_size', $size, strlen($data));
  $offset += $size;

  return $data;
}

// read a string (combination pascal length prefix and C null-terminator)
function readCString($stream, &$offset)
{
  $str = readSizedBlock($stream, $offset);
  check('c_string', "\0", substr($str, -1));

  return substr($str, 0, -1);
}

// MAIN HANDLER FOR INCOMING MESSAGES
if (empty($_GET["timestamp"])) {
  // Require a timestamp in query params
  _d("NO TIMESTAMP");
  echo "<H1>ERROR: Missing query parameter 'timestamp'</H1>";
} else if (empty($_COOKIE['HasbroCookieVR1'])) {
  // Require a cookie
  _d("NO COOKIE");
  echo "<H1>ERROR: Missing cookie parameter 'HasbroCookieVR1'</H1>";
} else {
  // Parse cookie into data and unpack
  _d("Hasbro cookie was: '" . $_COOKIE['HasbroCookieVR1'] . "'");
  $data = base64_decode($_COOKIE['HasbroCookieVR1']);

  if (! $data) {
    _d("Base64_decode(cookie) failed.");
    echo "<H1>ERROR: Failed to base64 decode 'HasbroCookieVR1'</H1>\n<BR>Contents of cookie:\n<BR><PRE>", $_COOKIE['HasbroCookieVR1'], "</PRE>";
  } else {
    // pick apart the data into components
    //  first portion is a HEADER with some info about the submission
    try {
      $offset = 0;

      $header_size = readU32($data, $offset);
      $game_type = readU32($data, $offset);
      $game_version = readU32($data, $offset);
      _d("Header size: $header_size, game_type: $game_type, version: $game_version");

      if (! array_key_exists($game_type, $games)) {
        throw new Exception("Assertion failure: Game type $game_type is not known!");
      } else {
        $game_handler = $games[$game_type];
      }

      // Following header-size, game-type and version, there are a series of U32
      //  these have game-specific meaning and are used by the mailer
      // There are always the same number (16)
      $meta_custom = array();
      _d("Meta custom at ($offset)");
      for ($i = 0; $i < 16; $i ++) {
        $meta_custom[$i] = readU32($data, $offset);
      }

      // the next 9 U32 in the Header are various indicators and flags
      //  these are semi-standard
      //  e.g. 0xC0FFEE00 always shows up in slot 5
      $meta_standard = array();
      _d("Meta standard at ($offset)");
      for ($i = 0; $i < 9; $i ++) {
        $meta_standard[$i] = readU32($data, $offset);
      }

      // META OVER!  Now we have the Payload.
      //  This is a sized block that we don't really touch or parse.
      _d("Reading payload starting at offset $offset");
      $payload = readSizedBlock($data, $offset);

      // Finally, there's info about the from- and to-address
      //  for the email we're sending.
      $player_count = readU32($data, $offset);
      check('player_count', 2, $player_count);

      $player = array();
      for ($i = 0; $i < $player_count; $i ++) {
        $player[$i]['name'] = readCString($data, $offset);
        $player[$i]['email'] = readCString($data, $offset);
      }

      // call the game-specific handler
      //  this should return an ID for the game,
      //  a message with info about the game,
      //  and a filename to use for the attached payload
      [ $id, $message, $filename ] = $game_handler($_GET['timestamp'], $meta_standard, $meta_custom, $payload, $player);

      // attempt to POST the response to Discord etc
      post_message($game_type, $id, $player[1]['email'], $message, $payload, $filename);

      // Build the response for the client

      // Construct response
      //  Put together the response we will tell the client
      //  1 = custom message, 0 = save game first?
      //  1 = custom error message, 0 = no error (so far)
      //  Unknown
      //  then 0xC0FFEE00 to indicate "success"
      $response = pack("N4", 0, 0, 1, 0x00EEFF0C);
      // Unknown 0x40 bytes
      for ($i = 0; $i < 0x40; $i ++) {
        $response .= pack("C", 0);
      }
      // 4x UINT32, purpose unknown, in network-byte-order
      for ($i = 0; $i < 4; $i ++) {
        $response .= pack("N", 0);
      }

      // unknown UINT32
      $response .= pack("N", 0);
      // unknown pair of uint32s
      //  these make the file extension for Message.???
      //  whatever that is - maybe opponent name? idk
      $response .= pack("Z8", "game");

      # Some kind of payload data, prefixed with a byte-length
      #  Guessing this is the game file
      $response .= pack('N', strlen($payload)); $response .= $payload;

      # Custom Message (message box) - MUST be multiple of 4 and zero-terminated
      # ordinarily this is zero though
      $response .= pack('N', 0);

      _d("Responding with: '" . base64_encode($response) . "'");
      echo "<PRE>\n", base64_encode($response), "\n</PRE>";
    } catch (Throwable $e) {
      _d("Caught exception: $e, Contents of cookie: '" . $_COOKIE['HasbroCookieVR1'] . "'");
      echo "<H1>ERROR: Failed to handle input from client</H1>\n<BR>Error was:\n<BR><PRE>", $e, "</PRE>\n<BR>Contents of cookie:\n<BR><PRE>", $_COOKIE['HasbroCookieVR1'], "</PRE>";
    }
  }
}
?>
</BODY></HTML>
