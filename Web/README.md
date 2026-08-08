# RadioBOSS Song Request System

**Version 1.5.0**

A configurable web-based song request system for RadioBOSS.

Version 1.5.0 adds a guided web setup wizard. New installations no longer need to edit `config.php` manually unless the administrator prefers the manual configuration method.

## Quick installation

1. Upload the release ZIP to your web server.
2. Extract it.
3. Open:

   ```text
   https://your-site.example/path/install.php
   ```

4. Follow the five setup steps.
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

## Upgrade from v1.4.x

1. Back up the existing installation.
2. Keep the existing `config.php`.
3. Replace the application files.
4. Compare the existing `config.php` with `config.example.php`.
5. Existing manual configurations can continue to be used.
6. `install.php` will remain locked while the existing `config.php` is present.

## Related project

RadioBOSS SongSync Engine:

https://github.com/mixnetwork1959/RadioBoss-SongSync-Engine

## License

MIT License.
