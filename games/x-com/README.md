# HasbroPBEMProxy: X-COM

* Product Code: 0x3939 (14649)
* Version: 0x10 (16)

[Read the Manual](./manual.html)

Email X-COM supports local proxy settings taken from Registry here:
`HKEY_LOCAL_MACHINE\SOFTWARE\Hasbro Interactive\Email X-COM`

The keys are 
* `bManualProxyEnable` (REG\_DWORD): `0x01`
* `ManualProxyName` (REG\_SZ): `hostname`
* `ManualProxyPort` (REG\_BINARY): `0x01 0x02` (port number, little-endian)

Steps:
* Install game
* From "Options" menu, go to "Advanced" tab
* Enter hostname and port, and check "Enabled"
