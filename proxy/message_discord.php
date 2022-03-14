<?php

// MAILER
//  send an update to Discord webhook

// config
$discord_url = array(
  0x00003939 => "https://discord.com/api/webhooks/223704706495545344/3d89bb7572e0fb30d8128367b3b1b44fecd1726de135cbe28a41f8b2f777c372ba2939e72279b94526ff5d1bd4358d65cf11",
  0x0000323e => "https://discord.com/api/webhooks/223704706495545344/3d89bb7572e0fb30d8128367b3b1b44fecd1726de135cbe28a41f8b2f777c372ba2939e72279b94526ff5d1bd4358d65cf11",
  0x0000321b => "https://discord.com/api/webhooks/223704706495545344/3d89bb7572e0fb30d8128367b3b1b44fecd1726de135cbe28a41f8b2f777c372ba2939e72279b94526ff5d1bd4358d65cf11",
  0x0000392a => "https://discord.com/api/webhooks/223704706495545344/3d89bb7572e0fb30d8128367b3b1b44fecd1726de135cbe28a41f8b2f777c372ba2939e72279b94526ff5d1bd4358d65cf11"
);
$db_filename = '49406.db';

// polyfill
class CURLStringFile extends CURLFile {
  public function __construct(string $data, string $postname, string $mime = "application/octet-stream") {
    $this->name     = 'data://'. $mime .';base64,' . base64_encode($data);
    $this->mime     = $mime;
    $this->postname = $postname;
  }
}

// attempt to POST the response to Discord etc
function post_message($game_type, $id, $players, $message, $attachment, $filename)
{
  // Discord alias support
  //  Use the alias.txt file, which is a CSV of from,to aliases.
  $aliases = file('./alias.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

  // Build a recipient list.
  //  Recipients are all entries 1 .. N from the Players array
  //  (Sender is entry 0)
  $discord_tag = array();
  for ($i = 0; $i < count($players) - 1; $i ++) {
    $discord_tag[$i] = preg_replace('/^@+/', '', $players[$i+1]['email']);
    $discord_tag[$i] = preg_replace('/\.+$/', '', $discord_tag[$i]);
    foreach($aliases as $alias) {
      $arr = explode(',', $alias);
      if ($arr[0] === strtolower($discord_tag[$i])) {
        $discord_tag[$i] = $arr[1];
      }
    }
    $discord_tag[$i] = "<@" . $discord_tag[$i] . ">";
  }

  // setup all common CURL options
  $opts = array(
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_FAILONERROR => true,
    CURLOPT_RETURNTRANSFER => true
  );

  // create curl resource
  $ch = curl_init();
  curl_setopt_array($ch, $opts);

  $url = $GLOBALS['discord_url'][$game_type];

  // build the POST fields
  curl_setopt($ch, CURLOPT_URL, $url . '?wait=true');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, array(
    'content' => implode(", ", $discord_tag) . "\n$message",
    'file' => new CURLStringFile($attachment, $filename)
  ));

  // $output contains the output string
  $output = curl_exec($ch);
  if (curl_errno($ch)) {
    // Failed to post the message to Discord...
    throw new Exception("PBEMProxy CURL error ($game_type, $id): " . curl_error($ch));
  } else {

    // having captured a webhook_id, we now need to delete any previous ones,
    //  then send a replace into at the db
    $result_decoded = json_decode($output, true);
    $webhook_id = $result_decoded['id'];

    // select the previous game from SQLITE DB
    $db = new SQLite3( $GLOBALS['db_filename'] );
    $db->enableExceptions(true);
    $db->busyTimeout(10000);

    $stmt = $db->prepare('SELECT webhook_id FROM game WHERE game_type=:game_type AND id=:id');
    $stmt->bindValue(':game_type', $game_type);
    $stmt->bindValue(':id', $id);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_NUM)) {
      curl_reset($ch);

      curl_setopt_array($ch, $opts);

      curl_setopt($ch, CURLOPT_URL, $url . '/messages/' . $row[0]);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      $curl_result = curl_exec($ch);
      if (curl_errno($ch)) {
        // Failed to delete the previous message from Discord...
        // This isn't fatal though.
        fwrite(STDERR, "PBEMProxy CURL error: " . curl_error($ch));
      }
    }
    $result->finalize();

    // send REPLACE query
    $stmt = $db->prepare('REPLACE INTO game(game_type, id, webhook_id) VALUES(:game_type, :id, :webhook_id)');
    $stmt->bindValue(':game_type', $game_type);
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':webhook_id', $webhook_id);
    $stmt->execute()->finalize();
    $db->close();
  }

  // close curl resource to free up system resources
  curl_close($ch);
}

?>
