# Changelog

## 1.5.0

- Added guided `install.php` web setup wizard.
- Added automatic creation of `config.php`.
- Added server requirement checks.
- Added optional RadioBOSS API connection test.
- Added optional second-station setup.
- Added independent API password support for both stations.
- Added automatic public/private data-directory creation.
- Added installer lock after successful configuration.
- Added automatic redirect to the installer when `config.php` is missing.
- Kept manual `config.example.php` installation fully supported.
- Clarified that SongSync is recommended but not mandatory when compatible catalog files are supplied separately.
- Updated documentation and project layout.

## 1.4.0

- Added central two-station request support.
- Added per-station catalog, lookup, API endpoint and runtime-file selection.
- Added station selection through the `station` query parameter.
- Added active-station guard support.
