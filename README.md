# Filer

Terminus plugin to open Pantheon Sites using an SFTP Client

Adds a sub-command to 'site' which is called 'filer'. This opens a site in your favorite SFTP Client.

## Supported

[Transmit](https://panic.com/transmit/) (Mac only)

[Cyberduck](https://cyberduck.io/) (Mac and Windows)

[Filezilla](https://filezilla-project.org/) (Mac, Linux and Windows)

[BitKinex](http://www.bitkinex.com/) (Windows only)

[WinSCP](https://winscp.net/) (Windows only)

## Examples
### Reference Application Name
`$ terminus site:filer companysite-33.dev --app=transmit`

### Reference Application Bundle Name
`$ terminus site:filer companysite-33.dev --bundle=com.panic.transmit`

### Shortcut for Panic's Transmit
`$ terminus site:transmit companysite-33.dev`

`$ terminus site:panic companysite-33.dev`

### Shortcut for Cyberduck
`$ terminus site:cyberduck companysite-33.dev`

`$ terminus site:duck companysite-33.dev`

### Shortcut for FileZilla
`$ terminus site:filezilla companysite-33.dev`

`$ terminus site:zilla companysite-33.dev`

### Shortcut for BitKinex
`$ terminus site:bitkinex companysite-33.dev`

`$ terminus site:bit companysite-33.dev`

### Shortcut for WinSCP
`$ terminus site:winscp companysite-33.dev`

`$ terminus site:scp companysite-33.dev`

## Installation
Create plugin directory
`mkdir ~/.terminus/plugins`
Install using composer
`composer create-project -d ~/.terminus/plugins terminus-plugin-project/terminus-filer-plugin:~1`

For help additional help installing, see [Terminus's Plugins](https://pantheon.io/docs/terminus/plugins/).

## Windows

Enviroment variables are available for Windows SFTP clients installed outside the standard `Program Files` directory:
```
BitKinex - TERMINUS_FILER_BITKINEX_LOC

Cyberduck - TERMINUS_FILER_CYBERDUCK_LOC

FileZilla - TERMINUS_FILER_FILEZILLA_LOC

WinSCP - TERMINUS_FILER_WINSCP_LOC
```

Make sure to include the full path to the executable (including the executable itself).

Example: `TEMINUS_FILER_BITKINEX_LOC="C:\BitKinex\bitkinex.exe"`

See http://www.computerhope.com/issues/ch000549.htm for information on how to set environment variables in Windows.

## Help
Run `terminus help site:filer` for help.

