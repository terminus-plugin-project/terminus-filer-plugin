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
`$ terminus site filer --site=companysite-33 --env=dev --a=transmit`

### Reference Application Bundle Name
`$ terminus site filer --site=companysite-33 --env=dev --b=com.panic.transmit`

### Shortcut for Panic's Transmit
`$ terminus site transmit --site=companysite-33 --env=dev`

`$ terminus site panic --site=companysite-33 --env=dev`

### Shortcut for Cyberduck
`$ terminus site cyberduck --site=companysite-33 --env=dev`

`$ terminus site duck --site=companysite-33 --env=dev`

### Shortcut for FileZilla
`$ terminus site filezilla --site=companysite-33 --env=dev`

`$ terminus site zilla --site=companysite-33 --env=dev`

### Shortcut for BitKinex
`$ terminus site bitkinex --site=companysite-33 --env=dev`

`$ terminus site bit --site=companysite-33 --env=dev`

### Shortcut for WinSCP
`$ terminus site winscp --site=companysite-33 --env=dev`

`$ terminus site scp --site=companysite-33 --env=dev`

## Installation
For help installing, see [Terminus's Wiki](https://github.com/pantheon-systems/terminus/wiki/Plugins).

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
Run `terminus help site filer` for help.
