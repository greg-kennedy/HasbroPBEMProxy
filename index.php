<HTML><TITLE>title</TITLE><BODY><PRE>
<?php

// config
$discord_url = "https://discord.com/api/webhooks/223704706495545344/3d89bb7572e0fb30d8128367b3b1b44fecd1726de135cbe28a41f8b2f777c372ba2939e72279b94526ff5d1bd4358d65cf11";
if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'wb'));

// PBEM response page

// polyfill
class CURLStringFile extends CURLFile {
    public function __construct(string $data, string $postname, string $mime = "application/octet-stream") {
        $this->name     = 'data://'. $mime .';base64,' . base64_encode($data);
        $this->mime     = $mime;
        $this->postname = $postname;
    }
}

function curl_del($url)
{

    return $result;
}

// helper parsing function
function check($name, $expected, $actual)
{
  if ($expected !== $actual) {
    //throw new Exception("Assertion failure: Value $name, expected $expected, got $actual");
    fwrite(STDERR, "Assertion failure: Value $name, expected $expected, got $actual\n");
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

    $data = substr($stream, $offset, $size);
    check('block_size', $size, strlen($data));
    $offset += $size;

    return $data;
}

function readCString($stream, &$offset)
{
    $str = readSizedBlock($stream, $offset);
    check('c_string', "\0", substr($str, -1));

    return substr($str, 0, -1);
}

$xem = '';
if (empty($_GET["timestamp"])) {
  // Require a timestamp in query params
  fwrite(STDERR, "PBEMProxy called with no Timestamp\n");
  $success = 0;
} else if (empty($_COOKIE['HasbroCookieVR1'])) {
  // Require a cookie
  fwrite(STDERR, "PBEMProxy called with no HasbroCookieVR1\n");
  $success = 0;
} else {
  // Parse cookie into data and unpack
  $data = base64_decode($_COOKIE['HasbroCookieVR1']);
  if (! $data) {
    fwrite(STDERR, "PBEMProxy failed to base64_decode cookie\n");
    $success = 0;
  } else {
    $offset = 0;

    // pick apart the data into components
    //  first portion is a HEADER with some info about the submission
    try {
      check('header_size', 0x70, readU32($data, $offset)); // header size

      check('game_type', 0x00003939, readU32($data, $offset)); // game_type, or protocol version

      $padding_count = readU32($data, $offset);
      check('padding_count', 0x10, $padding_count);
      $padding = array();
      for ($i = 0; $i < $padding_count; $i ++) {
        $padding[$i] = readU32($data, $offset);
        check('padding[' . $i . ']', 0x0, $padding[$i]);
      }

      // the next 9 U32 in the Header are various indicators and flags
      check('unknown1', 1, readU32($data, $offset));
      $game_accepted = readU32($data, $offset);
      $turn_number = readU32($data, $offset);
      $game_start = readU32($data, $offset);
      check('coffee', 0x00EEFF0C, readU32($data, $offset));
      check('unknown2', 1, readU32($data, $offset));
      check('unknown3', 0, readU32($data, $offset));
      $checksum1 = readU32($data, $offset); // unknown, random?
      $checksum2 = readU32($data, $offset); // unknown, random?

      // XEM file size and content
      $xem = readSizedBlock($data, $offset);

      $player_count = readU32($data, $offset);
      check('player_count', 2, $player_count);
      $player_name = array();
      $player_email = array();
      for ($i = 0; $i < $player_count; $i ++) {
        $player_name[$i] = readCString($data, $offset);
        $player_email[$i] = readCString($data, $offset);
      }

      $success = 1;
    } catch (Throwable $e) {
      fwrite(STDERR, "PBEMProxy error: $e\n");
      $success = 0;
    }
  }
}

if ($success) {
  // attempt to POST the response to Discord etc

  // build all CURL options
  $opts[CURLOPT_URL] = $discord_url . '?wait=true';
  $opts[CURLOPT_FOLLOWLOCATION] = true;
  $opts[CURLOPT_FAILONERROR] = true;
  $opts[CURLOPT_RETURNTRANSFER] = true;

  // build the POST fields
  $opts[CURLOPT_POST] = true;

  $player_index_0 = $turn_number % 2;
  $player_index_1 = 1 - $player_index_0;

  // select the previous game from SQLITE DB
  $db = new SQLite3('webhooks.db');
  $db->enableExceptions(true);
  $db->busyTimeout(10000);

  $stmt = $db->prepare('SELECT webhook_id FROM game WHERE timestamp=:timestamp AND player_0=:player_0 AND player_1=:player_1');
  $stmt->bindValue(':timestamp', $game_start);
  $stmt->bindValue(':player_0', $player_email[$player_index_0]);
  $stmt->bindValue(':player_1', $player_email[$player_index_1]);
  $result = $stmt->execute();
  $previous_webhook_ids = array();
  while ($row = $result->fetchArray(SQLITE3_NUM)) {
    $previous_webhook_ids[] = $row[0];
  }
  $result->finalize();

  $post_fields['content'] = sprintf(
    "%s **<@%s>: %s**\nGame: %s vs. %s\nTurn number: %d\n(Game start: <t:%d>)",
    ($game_accepted ? "💥" : "❓"), $player_email[1], ($game_accepted ? "IT'S YOUR TURN" : "GAME REQUESTED"),
    $player_name[$player_index_0], $player_name[$player_index_1],
    $turn_number,
    $game_start);

  $filename = sprintf("%010d_t%02d_%s_vs_%s.xem",
    $game_start,
    $turn_number,
    $player_name[$player_index_0],
    $player_name[$player_index_1]);
  
  $post_fields['file'] = new CURLStringFile($xem, $filename);
  $opts[CURLOPT_POSTFIELDS] = $post_fields;

  // create curl resource
  $ch = curl_init();
  if (false === curl_setopt_array($ch, $opts)) {
    fwrite(STDERR, "PBEMProxy CURL error: failed to set CURL_OPTS: " . curl_error($ch) . "\n");
    $success = 0;
  } else {
    // $output contains the output string
    $output = curl_exec($ch);
    if (curl_errno($ch)) {
      fwrite(STDERR, "PBEMProxy CURL error: " . curl_error($ch) . "\n");
      $success = 0;
    } else {

      // having captured a webhook_id, we now need to delete any previous ones,
      //  then send a replace into at the db
      $result_decoded = json_decode($output, true);
      $current_webhook_id = $result_decoded['id'];

      // use a separate curl handler to delete our previous posts
      $ch_del = curl_init();
      curl_setopt_array($ch_del, array(
        "CURLOPT_CUSTOMREQUEST" => "DELETE",
        "CURLOPT_FOLLOWLOCATION" => true,
        "CURLOPT_FAILONERROR" => true,
        "CURLOPT_RETURNTRANSFER" => true
      ));
      foreach ( $previous_webhook_ids as $prev_id ) {
        curl_setopt($ch_del, CURLOPT_URL, $discord_url . '/messages/' . $prev_id);
        $delete_result = curl_exec($ch_del);
        if (curl_errno($ch_del)) {
          fwrite(STDERR, "PBEMProxy CURL error: " . curl_error($ch_del) . " (" . $delete_result . ")\n");
	}
      }
      curl_close($ch_del);

      // send REPLACE query
      $stmt = $db->prepare('REPLACE INTO game(timestamp, player_0, player_1, webhook_id) VALUES(:timestamp, :player_0, :player_1, :webhook_id)');
      $stmt->bindValue(':timestamp', $game_start);
      $stmt->bindValue(':player_0', $player_email[$player_index_0]);
      $stmt->bindValue(':player_1', $player_email[$player_index_1]);
      $stmt->bindValue(':webhook_id', $current_webhook_id);
      $stmt->execute()->finalize();
      $db->close();
    }
  }

  // close curl resource to free up system resources
  curl_close($ch);     

}
// Build the response for the client

// Construct response
//  Put together the response we will tell the client
//  1 = custom message, 0 = save game first?
//  1 = custom error message, 0 = no error (so far)
//  Unknown
//  then 0xC0FFEE00 to indicate "success"
$response = pack("N4", 0, 1 - $success, 1, 0x00EEFF0C);
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
$response .= pack("Z8", "tempxem");

# Some kind of payload data, prefixed with a byte-length
#  Guessing this is the XEM
$response .= pack('N', strlen($xem)); $response .= $xem;

# Custom Message (message box) - MUST be multiple of 4 and zero-terminated
# ordinarily this is zero though
$response .= pack('N', 0);

echo base64_encode($response);

?>

</PRE></BODY></HTML>
