# HasbroPBEMProxy: Scrabble

* Product Code: 0x3211 (12817)
* Version: 0x40 (64)

[Read the Manual](./help.txt)

Email Scrabble will use system-wide proxy settings taken from Registry here:
`HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Internet Settings`

**Email Scrabble is a pre-HTTP game and uses SOCKS4 for proxy.**  See [the Socks4 readme](../../socks4/README.md) for more information.

The two keys are 
* ProxyServer - `REG_SZ` - "hostname:port"
* ProxyEnable - `REG_DWORD` - 0x01

Supplied IPS patch does four things:
* changes the read location to a local key instead: `HKEY_LOCAL_MACHINE\SOFTWARE\Hasbro Interactive\Email Scrabble`
* fixes access in `RegOpenKeyExA()` calls to allow Write as well as Read access
* fix infinite loop that occurs when closing the game and triggering `DISP_CHANGE_BADMODE` error message
* remove CD check

Steps:
* Apply IPS patch to `email-Scrabble.exe`
* Edit `Email Scrabble.reg` and set `ProxyServer` values to match desired proxy server
* Apply `Email Scrabble.reg` to create override entries in Registry

## Dictionary
Email Scrabble word validity checks are **entirely server-side**: the client does not ship with a dictionary.  In order to enable dictionary lookups, a (sorted) word list file must be placed in the same path as `game_scrabble.php`.  By default the expected filename is `CSW21.txt` (Collins Scrabble Words, 2021 edition), though this can be changed by editing the source file.
