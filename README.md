# RadioBOSS Song Request Bundle

**Bundle version 1.0.0**

Project repository:
[github.com/mixnetwork1959/RadioBOSS-Song-Request-Bundle](https://github.com/mixnetwork1959/RadioBOSS-Song-Request-Bundle)

RadioBOSS Song Request Bundle combines the two components required for a
complete, self-hosted song request system:

- **RadioBOSS SongSync Engine 1.6.0** runs on the RadioBOSS Windows computer.
- **RadioBOSS Song Request System 1.5.0** runs on a PHP web server.

The bundle is station-neutral. All station names, URLs, database settings,
RadioBOSS API credentials and server paths are entered during setup.

## How it works

1. SongSync reads the RadioBOSS SQLite or MySQL/MariaDB music library.
2. SongSync creates a public song catalog and a private filename lookup.
3. SongSync optionally uploads both sets of files to the web server by SFTP.
4. Visitors search the public catalog on the request website.
5. The web application uses the private lookup and sends the selected track to
   the RadioBOSS Remote Control API.

SongSync reads the RadioBOSS database but never changes it.

## Included components

| Directory | Version | Purpose |
| --- | ---: | --- |
| `SongSync` | 1.6.0 | Windows catalog export and optional SFTP upload |
| `Web` | 1.5.0 | PHP request page, request protection and RadioBOSS API connection |

Each component remains independently configurable and keeps its own version.
The bundle version only describes this tested combination.

## Setup applications

The bundle does not add a third installer. Use the two component setup tools:

1. Open `Web/install.php` in a browser after uploading the Web directory.
2. Run `SongSync/RadioBOSS-SongSync-Setup.exe` on the RadioBOSS computer.

For the complete installation order, see [QUICK-START.md](QUICK-START.md).

For SSH-key authentication, see
[docs/SFTP-SSH-KEY-SETUP.md](docs/SFTP-SSH-KEY-SETUP.md).

## Release ZIP and source code

The downloadable bundle release contains the three ready-to-run SongSync
Windows executables. A source checkout does not store generated EXE files;
developers can create them with `SongSync/build_windows.bat`.

## One or two stations

The Web component can serve a main station and an optional second station.
Each station needs its own SongSync configuration and its own RadioBOSS API
connection.

| Station | Public SFTP target | Private SFTP target | Request URL |
| --- | --- | --- | --- |
| Main | `Web/data/main/public` | `Web/data/main/private` | `index.php?station=main` |
| Secondary | `Web/data/secondary/public` | `Web/data/secondary/private` | `index.php?station=rock` |

When the stations run on different computers, install the SongSync folder on
each computer and run the setup wizard separately. The generated `config.py`
belongs to that computer and must not be copied between stations unless all
database and path settings are reviewed.

## Requirements

### RadioBOSS computer

- Windows 10 or Windows 11
- RadioBOSS with a SQLite or MySQL/MariaDB music library
- Windows OpenSSH for automated SSH-key SFTP uploads
- Network access to the web server

### Web server

- PHP 8.0 or newer
- PHP cURL and JSON support
- SFTP access when automatic catalog upload is used
- Network access from PHP to the RadioBOSS Remote Control API

## Security

Never publish or commit:

- `SongSync/config.py`
- SSH private keys or passwords
- `SongSync/sftp_known_hosts`
- `Web/config.php`
- private `lookup.json` files
- request state files or request logs

The Web package includes Apache `.htaccess` protection for private data
directories. Nginx and other web servers require an equivalent deny rule.

The SSH private key stays on the RadioBOSS computer. Only its public `.pub` key
is added to the server's `~/.ssh/authorized_keys` file.

## License

MIT License. See [LICENSE](LICENSE).
