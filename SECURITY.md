# Security Policy

## Private data

Never publish passwords, RadioBOSS API credentials, SSH private keys,
`config.py`, `config.php`, `lookup.json`, request state files or request logs.

If private data is accidentally committed, remove it from the repository and
rotate the affected password or key immediately. Deleting the latest copy does
not remove a secret from Git history.

## Reporting a vulnerability

Use GitHub's private security advisory function when available. Do not include
working credentials, private keys or personal listener data in a public issue.
