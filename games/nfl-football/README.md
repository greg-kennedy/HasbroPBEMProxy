# HasbroPBEMProxy: NFL Football

* Product Code: 0x321b (12827)
* Version: 0x40 (64)

[Read the Manual](./help.txt)

Email NFL Football will use system-wide proxy settings taken from Registry here:
`HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Internet Settings`

The two keys are 
* ProxyServer - `REG_SZ` - "hostname:port"
* ProxyEnable - `REG_DWORD` - 0x01

Supplied IPS patch does two things:
* changes the read location to a local key instead: `HKEY_LOCAL_MACHINE\SOFTWARE\Hasbro Interactive\EFootball`
* fixes access in `RegOpenKeyExA()` calls to allow Write as well as Read access

Steps:
* Apply IPS patch to `email-NFL Football.exe`
* Edit `EFootball.reg` and set `ProxyServer` values to match desired proxy server
* Apply `EFootball.reg` to create override entries in Registry
