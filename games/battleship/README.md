# HasbroPBEMProxy: Battleship

* Product Code: 0x3216 (12822)
* Version: 0x40 (64)

[Read the Manual](./help.txt)

Email Battleship will use system-wide proxy settings taken from Registry here:
`HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Internet Settings`

**Email Battleship is a pre-HTTP game and uses SOCKS4 for proxy.**  See [the Socks4 readme](../../socks4/README.md) for more information.

The two keys are 
* ProxyServer - `REG_SZ` - "socks=hostname:port"
* ProxyEnable - `REG_DWORD` - 0x01

Supplied IPS patch does four things:
* changes the read location to a local key instead: `HKEY_LOCAL_MACHINE\SOFTWARE\Hasbro Interactive\Email Battleship`
* fixes access in `RegOpenKeyExA()` calls to allow Write as well as Read access
* fix infinite loop that occurs when closing the game and triggering `DISP_CHANGE_BADMODE` error message
* remove CD check

Steps:
* Apply IPS patch to `email-Battleship.exe`
* Edit `Email Battleship.reg` and set `ProxyServer` values to match desired proxy server
* Apply `Email Battleship.reg` to create override entries in Registry
