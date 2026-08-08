# Contributing

Contributions that improve compatibility, documentation, security or setup are
welcome.

## Before submitting a change

1. Keep the bundle station-neutral.
2. Do not commit passwords, API credentials, private SSH keys, `config.py`,
   `config.php`, generated catalogs or private lookup files.
3. Preserve the separation between the Windows SongSync component and the PHP
   Web component.
4. Update `CHANGELOG.md` when behavior changes.
5. Run the available Python, PHP, JavaScript and JSON syntax checks.

## Component versions

The versions in `bundle.json` identify the tested component combination. A
component update does not automatically change the other component's version,
but it requires a new bundle version and an updated changelog.
