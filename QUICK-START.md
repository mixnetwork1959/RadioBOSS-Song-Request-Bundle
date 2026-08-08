# Quick Start

This guide installs RadioBOSS Song Request Bundle 1.0.0 for one station. A
second station is added in the final section.

## 1. Prepare RadioBOSS

1. Enable the RadioBOSS Remote Control API.
2. Set a strong API password.
3. Note the API host, port and password.
4. Confirm that the PHP web server can reach the API address.

`127.0.0.1` works only when PHP and RadioBOSS run on the same computer. For a
remote web server, use a securely reachable hostname or IP address and protect
the exposed API with appropriate firewall rules.

## 2. Install the Web component

1. Upload the contents of the `Web` directory to the desired web directory.
2. Open the installer, for example:

   ```text
   https://your-site.example/songrequest/install.php
   ```

3. Complete the five setup steps.
4. Enter the public catalog URL:

   ```text
   data/main/public/songs.json
   ```

5. Enter the RadioBOSS API URL and password.
6. Use the API test in the wizard.
7. Finish the installation and open `index.php`.

The installer creates `config.php` and prepares:

```text
data/main/public
data/main/private
```

Once `config.php` exists, the installer locks itself and will not overwrite the
configuration.

## 3. Install SongSync on Windows

1. Copy the `SongSync` directory to a permanent location, for example:

   ```text
   C:\RadioBOSS-SongSync
   ```

2. Run:

   ```text
   RadioBOSS-SongSync-Setup.exe
   ```

3. Select the database type:

   - `SQLite` for the standard RadioBOSS database, or
   - `MySQL / MariaDB` when RadioBOSS uses a database server.

4. Select or detect the database and run the database test.
5. Keep the default local export directories unless another location is
   required.

The setup wizard writes `config.py` beside the executable. This file contains
private settings and must not be shared.

## 4. Configure the SFTP upload

Enable SFTP in the SongSync setup wizard and enter:

- SFTP host and port
- SFTP username
- password or SSH private-key file
- remote public directory ending in `data/main/public`
- remote private directory ending in `data/main/private`

The remote paths are server filesystem paths, not public website URLs. They
must already exist and be writable by the SFTP account.

For password authentication, leave the private-key field empty.

For SSH-key authentication, follow
[docs/SFTP-SSH-KEY-SETUP.md](docs/SFTP-SSH-KEY-SETUP.md) before testing the
connection.

Keep **Trust server key on first successful connection** enabled for the normal
first-time setup. SongSync records the server identity in
`sftp_known_hosts` and rejects an unexpected identity change later.

Run the SFTP test and save the configuration.

## 5. Run the first synchronization

Start:

```text
RadioBOSS-SongSync.exe
```

The normal executable runs in the background and writes details to:

```text
songsync.log
```

After a successful run, the web server should contain:

```text
data/main/public/songs.json
data/main/public/artists.json
data/main/public/genres.json
data/main/public/info.json
data/main/private/lookup.json
```

Open the request page and search for a known artist or title. Send one test
request and confirm that RadioBOSS receives it.

For troubleshooting with a visible console, run:

```text
RadioBOSS-SongSync-Debug.exe
```

## 6. Automate SongSync

After the manual test succeeds, add `run_songsync.bat` or
`RadioBOSS-SongSync.exe` to a RadioBOSS scheduled event. A daily update is a
practical starting point; run it more often when the library changes
frequently.

Do not schedule the setup executable.

## 7. Add a second station

1. Enable the second station in the Web setup wizard.
2. Install and configure SongSync for the second RadioBOSS library.
3. Use these SFTP targets:

   ```text
   data/secondary/public
   data/secondary/private
   ```

4. Test the secondary RadioBOSS API connection.
5. Open:

   ```text
   index.php?station=rock
   ```

Each SongSync installation keeps its own local `config.py`.
