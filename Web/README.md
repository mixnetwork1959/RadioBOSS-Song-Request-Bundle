# RadioBOSS Song Request System

**Version 1.6.0**

A configurable web-based song request system for RadioBOSS.

Version 1.6.0 adds hourly artist request protection while keeping the existing
request queue, request slots and ETA logic unchanged. The guided web setup
wizard introduced in v1.5.0 remains the recommended installation method.

## Quick installation

1. Upload the release files to your web server.
2. Extract them if required.
3. Open:

   ```text
   https://your-site.example/path/install.php
   ```

4. Follow the setup steps.
5. The wizard creates `config.php` and prepares the private/public data directories.
6. Open `index.php` and test the request page.

Once `config.php` exists, `install.php` is automatically locked and will not overwrite the existing configuration.

## Setup Wizard

The wizard guides the administrator through:

1. PHP/server requirements
2. Main and optional secondary station
3. Song catalog and RadioBOSS Remote Control API
4. Request timing and protection rules
5. Review and installation

The RadioBOSS step includes an optional API connection test.

## Hourly artist request protection

Hourly artist protection is enabled by default with:

```php
define('ARTIST_HOURLY_PROTECTION', true);
```

If a song by an artist has already been accepted as a request during the
current station clock hour, another request for the same artist on the same
station is rejected, even when a different title is selected.

The rule:

- uses the configured `STATION_TIMEZONE`
- resets at the start of the next clock hour
- is applied separately for the main and secondary station
- does not replace the existing IP, track or hourly request limits
- returns a listener-friendly rejection message

Example: an artist requested at 14:12 is blocked for additional requests until
15:00 on that station.

## Manual configuration

Manual installation remains supported.

Copy:

```text
config.example.php
```

to:

```text
config.php
```

and edit the settings directly.

## Two-station support

One installation can serve a main and secondary RadioBOSS station.

```text
index.php?station=main
index.php?station=rock
```

Each station can use its own:

- public `songs.json`
- private `lookup.json`
- RadioBOSS API URL
- RadioBOSS API password
- request state
- request log

The second station is optional and can be disabled in the wizard.

## Song catalog

The request website requires a compatible public song catalog and private filename lookup.

The **RadioBOSS SongSync Engine is recommended** because it can generate and update these files automatically, but SongSync is not a hard requirement if compatible JSON files are supplied by another method.

Default wizard locations:

```text
data/main/public/songs.json
data/main/private/lookup.json
```

Secondary station:

```text
data/secondary/public/songs.json
data/secondary/private/lookup.json
```

## Requirements

- PHP 8.0 or newer
- PHP cURL extension
- PHP JSON support
- RadioBOSS Remote Control API
- Web server access to the RadioBOSS API
- Write permission to the project directory during setup

## Security

Never publish:

- `config.php`
- RadioBOSS API passwords
- `lookup.json`
- request state files
- request logs

Private data directories include an Apache `.htaccess` rule which denies public access. On Nginx or other web servers, configure equivalent protection at server level.

After installation the wizard refuses to overwrite `config.php`. To intentionally reinstall, the administrator must manually remove `config.php`.

## Upgrade from v1.5.x

1. Back up the existing installation.
2. Keep the existing `config.php`.
3. Replace the application files.
4. Compare the existing `config.php` with `config.example.php`.
5. Add `ARTIST_HOURLY_PROTECTION` if it is not already present; it defaults to enabled in new installations.
6. Existing request slots, queue state and ETA behavior remain unchanged.
7. `install.php` remains locked while the existing `config.php` is present.

## License

MIT License.
