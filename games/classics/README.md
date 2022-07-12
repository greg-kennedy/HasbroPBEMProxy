# HasbroPBEMProxy: Classic Games (Grandmaster Chess, Checkers, and Backgammon)

* Product Codes:
  * Chess: 0x3902 (14594)
  * Checkers: 0x3907 (14599)
  * Backgammon: 0x390c (14604)
* Version: 0x10 (16)

Read the Manuals:
* [Chess](./help-Chess.txt)
* [Checkers](./help-Checkers.txt)
* [Backgammon](./help-Backgammon.txt)

Email Classic Games installs three separate game applications, as well as a Launcher for selecting which to play.  The launcher is not necessary for playing a game.  All three game executables share common code and require similar fixes.

By default, the Email Classics games will use system-wide proxy settings taken from Registry here:
`HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Internet Settings`

The two keys are 
* ProxyServer - `REG_SZ` - "hostname:port"
* ProxyEnable - `REG_DWORD` - 0x01

Supplied IPS patches do four things:
* changes the read location to a local key instead: `HKEY_LOCAL_MACHINE\SOFTWARE\Hasbro Interactive\Email (Game)`
* disables CD check when launching a new game
* fixes access in `RegOpenKeyExA()` calls to allow Write as well as Read access
* fix `ChangeDisplaySettingsA()` call to correctly restore display settings on exit (avoids BADMODE error)

Steps:
* Apply IPS patch to `email-(Game).exe`
* Edit `Email (Game).reg` and set `ProxyServer` values to match desired proxy server
* Apply `Email (Game)..reg` to create override entries in Registry
