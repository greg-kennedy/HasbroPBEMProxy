<?php
  // set some variables for configuration
  $host = "0.0.0.0";
  $port = 12346;

  // destination server for HTTP proxy service
  $remote_host = 'localhost';
  $remote_port = 12345;

  $socks_allow_list = [
    'VER' => [ 0x04 ],
    'CMD' => [ 0x01 ],
    'DSTPORT' => [ 0x50 ],
    'DESTIP' => [ 0x800B294C, 0x800B294D ],
    'ID' => [ 'Hasbro' ]
  ];

  // Helper function to turn socket errors into exceptions
  function err(string $message, $socket = null) {
    if(is_resource($socket)) {
        $message .= ": " . socket_strerror(socket_last_error($socket));
    }
    throw new Exception($message);
  }

  // allow script to run forever
  set_time_limit(0);

  // Create socket
  $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Could not create socket\n");
  socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
  // Bind socket to port
  $result = socket_bind($socket, $host, $port) or die("Could not bind to socket\n");
  // Start listening for connections
  $result = socket_listen($socket, 3) or die("Could not set up socket listener\n");

  print "Bound to socket and listening for connections.\n";

  // loop to keep the service alive except really fatal errors
  $done = 0;
  while (! $done) {
    // Accept incoming connections
    // spawn another socket to handle communication
    while ( $spawn = socket_accept($socket) ) {
      print "Accepted connection on new socket\n";
      // set timeout on spawn socket to prevent hanging
      socket_set_option($spawn,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>5, "usec"=>0));

      // Read client input
      //  first 4 bytes are enough to tell us some info - whether it's SOCKS4 or payload
      try {
        $input = socket_read($spawn, 4) or err("Could not read input 4 bytes", $spawn);

        $test_socks = unpack("CVER/CCMD/nDSTPORT", $input);

        if (
          in_array($test_socks['VER'], $socks_allow_list['VER']) &&
          in_array($test_socks['CMD'], $socks_allow_list['CMD']) &&
          in_array($test_socks['DSTPORT'], $socks_allow_list['DSTPORT'])
        ) {
          print "Detected SOCKS4 proxy, answering...\n";

          // this seems to be a SOCKS4 wrapped connection!
          //  get dest IP
          $input = socket_read($spawn, 4) or err("Could not read SOCKS4 IP", $spawn);
          $DESTIP = unpack("N", $input);
          //  read the null-terminated ID
          $id = '';
          while (strlen($id) < 512) {
            $input = socket_read($spawn, 1) or err("Could not read SOCKS4 ident", $spawn);
            if (ord($input) == 0) {
              break;
            } else {
              $id .= $input;
            }
          }

          // check if Dest IP and ID are valid too
          if (in_array($DESTIP[1], $socks_allow_list['DESTIP']) &&
              in_array($id, $socks_allow_list['ID'])) {
            print "Accepted SOCKS4 request from $id\n";
            // send a success message
            socket_write($spawn, pack("CCnN", 0, 0x5A, 0, 0), 8) or err("Could not write SOCKS4 accept response", $spawn);
            // read 4 more bytes to replace the SOCKS packet
            $input = socket_read($spawn, 4) or err("Could not re-read input 4 bytes", $spawn);
          } else {
            // send a failed message
            print "Declined SOCKS4 request from $id\n";
            socket_write($spawn, pack("CCnN", 0, 0x5B, 0, 0), 8) or err("Could not write SOCKS4 fail response", $spawn);
            //socket_close($spawn);
            //continue;
            throw new Exception("Invalid SOCKS4 request, disconnecting client.");
          }
        }

        // We only really support certain packets of size 0x70 header
        //  so reject anything that doesn't fit that
        // payload is made up of 3 "blocks"
        //  read them all and assemble for base64 call
        $payload_size = unpack("N", $input)[1] - 4;
        if ($payload_size == 0x6C) {
          $payload = $input;
          // read rest of payload A
          print "Reading payload header, size " . $payload_size . "\n";
          $input = socket_read($spawn, $payload_size) or err("Could not read headers", $spawn);
          $payload .= $input;

          // second block, the TURN portion
          $input = socket_read($spawn, 4) or err("Could not read payload size", $spawn);
          $payload .= $input;
          $payload_size = unpack("N", $input)[1];
          print "Reading payload SEM, size " . $payload_size . "\n";
          $input = socket_read($spawn, $payload_size) or err("Could not read payload", $spawn);
          $payload .= $input;

          // final blocks - email header
          // player count
          $input = socket_read($spawn, 4) or err("Could not read player count", $spawn);
          $payload .= $input;
          $player_count = unpack("N", $input);
          for ($i = 0; $i < 2 * $player_count[1]; $i ++) {
            $input = socket_read($spawn, 4) or err("Could not read player info string size", $spawn);
            $payload .= $input;
            print "Reading payload string, size " . $payload_size . "\n";
            $payload_size = unpack("N", $input)[1];
            $input = socket_read($spawn, $payload_size) or err("Could not read player info string size", $spawn);
            $payload .= $input;
          }

          print "Finished.  Size: " . strlen($payload) . "\n";
          //print "---\n" . bin2hex($payload) . "\n---\n";

          // WE MAY NOW MAKE HTML CONNECTION TO BACKEND
          $timestamp = explode(' ', microtime(), 2);
          $remote_url = sprintf('http://%s:%u/%u/%u.htm',
            $remote_host,
            $remote_port,
            $timestamp[1],
            floor($timestamp[0] * 1000)
          );
          print "Making request to $remote_url\n";

          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $remote_url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_FAILONERROR, true);
          curl_setopt($ch, CURLOPT_COOKIE, 'HasbroCookieVR1=' . base64_encode($payload));
          $data = curl_exec($ch);
          if(curl_errno($ch)){
            $message = 'Request Error: ' . curl_error($ch);
            curl_close($ch);
            throw new Exception($message);
          }
          curl_close($ch);

          print "Received response:\n---\n$data\n---\n";

          // pluck out response code
          if (preg_match('{<pre>\s*([A-Za-z0-9+=/]+)\s*</pre>}i', $data, $response)) {
            $output = base64_decode($response[1]);

            print "Responding with " . strlen($output) . " bytes to client\n";
            //print "---\n" . bin2hex($output) . "\n---\n";
            socket_write($spawn, $output, strlen ($output)) or err("Could not write final packet to client", $spawn);
          } else {
            throw new Exception("Did not get a valid response from server");
          }
        } else {
          throw new Exception("Payload size not 0x70, will not continue parsing.");
        }

        print "All done!  Disconnecting client.\n";
      } catch (Exception $e) {
        print "Caught exception processing socket input: " . $e->getMessage() . "\n";
      } finally {
        // Close sockets
        socket_close($spawn);
      }
    }

    // this is reached on error in socket_accept, usually SIGALRM or something
    //  that's not fatal but needs the call to happen again
    $error = socket_last_error($socket);

    if ($error && $error !== SOCKET_EINTR && $error !== SOCKET_EAGAIN) {
      // don't know how to handle the error so exit
      print "Fatal error in socket_accept(): $error: " . socket_strerror($error) . "\n";
      $done = 1;
    }
  }

  socket_close($socket);

?>
