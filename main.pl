#!/usr/bin/env perl
use strict;
use warnings;

# global config
use constant SERVER_PORT => 8080;

# includes
use MIME::Base64 qw(decode_base64 encode_base64);
use IO::Socket::INET qw(:DEFAULT :crlf);

# helper function
#  format a timestamp as RFC822
sub rfc822_gm {
    my ($epoch) = @_;

    my @time = gmtime $epoch;

    my @month_names = qw(Jan Feb Mar Apr May Jun
                         Jul Aug Sep Oct Nov Dec);
    my @day_names = qw(Sun Mon Tue Wed Thu Fri Sat Sun);

    return sprintf('%s, %02u %s %04u %02u:%02u:%02u +0000',
                   $day_names[$time[6]], $time[3], $month_names[$time[4]],
                   $time[5] + 1900, $time[2], $time[1], $time[0]);
}

# main / init
my $server = IO::Socket::INET->new(
  LocalPort => SERVER_PORT,
  Type => SOCK_STREAM,
  Reuse => 1,
  Listen => 10 ) # or SOMAXCONN
or die "Couldn't be a tcp server on port " . SERVER_PORT . ": $@\n";

print "Waiting for connections...\n";

# Loop with accept, this will block until someone makes a conn.
while (my ($client,$client_address) = $server->accept())
{
  print " . Received connection from $client_address\n";

  my $req_done = 0;
  my %headers;
  my %cookies;
  while (! $req_done) {
    my $line = <$client>;
    # parse headers / request
    if ($line =~ m/^GET\s+(\S+)\s+(\S+)\s+$/) {
      print " . GET host=$1 protocol=$2\n";
    } elsif ($line =~ m/^cookie:\s+(.*?)=(.*?)\s+$/i) {
      $cookies{$1} = $2;
    } elsif ($line =~ m/^(\S+):\s+(.*?)\s+$/) {
      $headers{lc($1)} = $2;
    } else {
      $req_done = 1;
    }
  }

  if (! $cookies{HasbroCookieVR1}) {
    # errors can be reported here
    #  probably wise to figure out the code for "reject move" (e.g. 0xB0FFEE)
    #  and put it in here to report the specific error
    print $client "HTTP/1.0 400 Bad Request$CRLF";
    print $client "Date: " . rfc822_gm(time()) . $CRLF;
    print $client "Server: pbem-proxy.pl$CRLF";
    print $client "Content-Length: 0$CRLF$CRLF";
  } else {
    print " . Received cookie: " . $cookies{HasbroCookieVR1} . "\n";

    # parse cookie
    my $data = decode_base64($cookies{HasbroCookieVR1});

    # header block
    my $i = 0;
    my ($header_size, $type, $padding) = unpack( 'NNN', substr($data, 0, 12) );
    $i += 12;
    print "Header size: $header_size\nType: $type\nPadding: $padding\n";

    for my $j (1 .. $padding) {
      my $zero = unpack 'N', substr($data, $i, 4);
      if ($zero != 0) {
        warn "Padding $j, expected zero, got $zero\n";
      }
      $i += 4;
    }

    my ($packet_type, $player_number, $team_number, $timestamp, $coffee, $one, $zero, $ck0, $ck1) = unpack('NNNNNNNNN', substr($data, $i, 4 * 9));
    print "$packet_type\n$player_number\n$team_number\n" . localtime($timestamp) . "\n$coffee\n$one\n$zero\n$ck0 $ck1\n";
    $i += 4*9;

    my $xem_size = unpack('N', substr($data, $i, 4));
    $i += 4;
    print "XEM block size: $xem_size\n";

    my $xem = substr($data, $i, $xem_size);
    $i += $xem_size;

    my $form2 = unpack('N', substr($data, $i, 4)); $i += 4;
    warn "form2 = $form2" unless $form2 == 2;

    my $name1_length = unpack('N', substr($data, $i, 4)); $i += 4;
    my $name1 = unpack('Z*', substr($data, $i, $name1_length)); $i += $name1_length;
    my $email1_length = unpack('N', substr($data, $i, 4)); $i += 4;
    my $email1 = unpack('Z*', substr($data, $i, $email1_length)); $i += $email1_length;
    my $name2_length = unpack('N', substr($data, $i, 4)); $i += 4;
    my $name2 = unpack('Z*', substr($data, $i, $name2_length)); $i += $name2_length;
    my $email2_length = unpack('N', substr($data, $i, 4)); $i += 4;
    my $email2 = unpack('Z*', substr($data, $i, $email2_length)); $i += $email2_length;

    print "From: $name1 <$email1>\nTo: $name2 <$email2>\n";

    print $client "HTTP/1.0 200 OK$CRLF";
    print $client "Date: " . rfc822_gm(time()) . $CRLF;
    print $client "Server: pbem-proxy.pl$CRLF";
    print $client "Content-Type: text/html$CRLF";

    # Put together the response we wil ltel lthe client
    # 1 = custom message, 0 = save game first?
    # 1 = custom error message, 0 = no error (so far)
    # Unknown
    # then 0xC0FFEE00 to indicate "success"
    my $reply_data = pack('NNNN', 0, 0, 1, 0x00EEFF0C);
    # Unknown 40 bytes
    for my $i (1 .. 0x10) {
      $reply_data .= pack 'N', 0;
    }
    # the next 4 are byteswapped from htonl, seems important
    for my $i (1 .. 0x04) {
      $reply_data .= pack 'N', 0;
    }
    #  unknown uint32
    $reply_data .= pack 'N', 0;
    # unknown pair of uint32s
    #  these make the file extension for Message.
    #  whatever that is.  maybe opponent name, who knows
    $reply_data .= pack 'Z8', 'tempxem';

    # Some kind of payload data, prefixed with a byte-length
    #  Guessing this is the XEM
    $reply_data .= pack 'N', $xem_size; $reply_data .= $xem;

    # Custom Message (message box) - MUST be multiple of 4 and zero-terminated
    # ordinarily this is zero though
    $reply_data .= pack 'N', 0;
    # use this instead and set Custom Error above
    #$reply_data .= pack 'N', 4; $reply_data .= pack 'N', 0x79753300;

    # build response
    my $response = "<HTML><TITLE>title</TITLE><BODY><PRE>$CRLF" . encode_base64($reply_data) . "</PRE></BODY></HTML>$CRLF";

    print $client "Content-Length: " . length($response) . "$CRLF$CRLF";
    print $client $response;

    print "Sent response: $response";

    # TODO
    #  HERE YOU SHOULD SEND $xem TO $email2 SOMEHOW
  }
  print " . Closing connection.\n";
  close $client or die "close: $!";
}

close($server);
