# HasbroPBEMProxy: NASCAR

* Product Code: 0x323e (12862)
* Version: 0x10 (16)

[Read the Manual](./help.html)

Email NASCAR will use system-wide proxy settings taken from Registry here:
`HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Internet Settings`

The two keys are 
* ProxyServer - `REG_SZ` - "hostname:port"
* ProxyEnable - `REG_DWORD` - 0x01

Supplied IPS patch changes the read location to a local key instead:
`HKEY_LOCAL_MACHINE\SOFTWARE\Hasbro Interactive\Email NASCAR`

Steps:
* Apply IPS patch to `Email NASCAR.exe`
* Edit `NASCARProxy.reg` and set `ProxyServer` values to match desired proxy server
* Apply `NASCARProxy.reg` to create override entries in Registry
