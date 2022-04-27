# HasbroPBEMProxy: Clue / Cluedo

* Product Code: 0x392a (14634)
* Version: 0x10 (16)

[Read the Manual](./email-Cluedo%20Help.html)

Email Clue will use system-wide proxy settings taken from Registry here:
`HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Internet Settings`

The two keys are 
* ProxyServer - `REG_SZ` - "hostname:port"
* ProxyEnable - `REG_DWORD` - 0x01

Supplied IPS patch does two things:
* changes the read location to a local key instead: `HKEY_LOCAL_MACHINE\SOFTWARE\Hasbro Interactive\Email Clue`
* disables CD check when launching a new game

Steps:
* Apply IPS patch to `email-Cluedo.exe`
* Edit `Email Clue.reg` and set `ProxyServer` values to match desired proxy server
* Apply `Email Clue.reg` to create override entries in Registry
