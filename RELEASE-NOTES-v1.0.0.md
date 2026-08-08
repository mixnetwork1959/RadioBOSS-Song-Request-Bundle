# RadioBOSS Song Request Bundle v1.0.0

The first public bundle release combines the complete Windows catalog-sync and
PHP song-request workflow in one neutral package.

## Included

- RadioBOSS SongSync Engine v1.6.0
- RadioBOSS Song Request System v1.5.0
- Ready-to-run Windows executables
- Guided SongSync and Web setup applications
- SQLite and MySQL/MariaDB support
- SFTP password and SSH-key authentication
- One- and two-station configuration
- Detailed quick-start and SSH-key documentation

## Installation

1. Extract the bundle.
2. Upload the `Web` directory and open `install.php`.
3. Copy the `SongSync` directory to the RadioBOSS Windows computer.
4. Run `RadioBOSS-SongSync-Setup.exe`.
5. Test the database, SFTP upload and RadioBOSS request connection.

See `QUICK-START.md` for the complete installation order.

## Security

The release contains no station credentials, private SSH keys, generated song
catalogs or private lookup data. The SSH private key remains on the RadioBOSS
computer; only the public key is installed on the SFTP server.

## Downloads

- `RadioBOSS-Song-Request-Bundle-v1.0.0.zip` — ready-to-use bundle
- `RadioBOSS-Song-Request-Bundle-v1.0.0-Source.zip` — complete source code
- `RadioBOSS-Song-Request-Bundle-v1.0.0.sha256.txt` — SHA-256 checksums
