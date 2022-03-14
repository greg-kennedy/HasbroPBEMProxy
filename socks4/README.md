# HasbroPBEMProxy/socks4
_Greg Kennedy, 2022_

## Details
The earliest Hasbro games did not use an HTTP connection to talk with the (web-based) email server.  Instead, they used a custom TCP server which directly exchanged game structures.  Later when the HTTP server was added, the devs continued to use the same messaging format, but wrapped it in base64 encoding and transmitted it as the contents of `HasbroCookieVR1`.

Because of this, older games that do not work with the web server require special handling.
* The "proxy" to reach the server IP is [SOCKS4](https://en.wikipedia.org/wiki/SOCKS), not HTTP.
* Requests need to be wrapped in the HTTP request format, and unwrapped when sending the response codes.

`main.php` uses the PHP Sockets extension to create a fake SOCKS4 proxy and will answer requests from Hasbro games.  It will take the traffic from the client, construct a cURL request to the Hasbro HTTP server, gather and decode the response, and relay this back to the client.  Non-Hasbro requests are denied immediately.

Run the server with `nohup` to create a persistent daemon for handling requests.

Note that this is a *blocking* server with 5-second timeout and is easily overloaded - not suitable for large traffic.

## Configuration
Edit the top of `main.php` and set `$port` to the listen port of the TCP server, `$remote_host` to the URL of your web server, and `$remote_port` to the port of the web server.  Note that `$port` and `$remote_port` must be different!

## Supported Games
The following games are currently supported by the proxy:

* Email Scrabble
