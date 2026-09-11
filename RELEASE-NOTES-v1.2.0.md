# RadioBOSS Song Request Bundle v1.2.0

Shop-ready station-neutral release.

## Included

- RadioBOSS SongSync Engine 1.8.0
- RadioBOSS Song Request System 1.6.0
- Ready-to-run SongSync Windows executables
- Guided Web and SongSync setup tools
- Hourly artist request protection per station and station clock hour
- Existing request slots, queue position and ETA behavior preserved
- Customer-facing `HELP.html`
- Updated `QUICK-START.md`

## Artist repeat protection

A second accepted request for the same artist on the same station is blocked
until the next station clock hour, even when the listener selects a different
song. The rule uses the configured station timezone and returns a clear
listener message.

## Shop package

The Shop ZIP contains only customer-facing runtime files and documentation.
Developer-only GitHub workflow and test files are not included.

The package contains no station-specific names, station domains, API passwords
or private server paths. Customers enter their own settings during setup.
