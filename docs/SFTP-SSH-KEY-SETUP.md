# SFTP and SSH-Key Setup

RadioBOSS SongSync supports SFTP with either a password or an SSH private key.
SSH keys are recommended for unattended scheduled uploads when the hosting
provider supports them.

## Password or SSH key

### Password authentication

Enter the SFTP username and password in the SongSync setup wizard and leave the
private-key field empty. This is the simplest method, but the password is stored
locally in `config.py`.

### SSH-key authentication

An SSH key consists of two files:

- The **private key** stays on the RadioBOSS computer.
- The **public key** ends in `.pub` and is added to the web server.

Never upload the private key to the web server and never share it.

## 1. Check Windows OpenSSH

Open Command Prompt and run:

```bat
where ssh-keygen
where sftp
```

The usual Windows paths are:

```text
C:\Windows\System32\OpenSSH\ssh-keygen.exe
C:\Windows\System32\OpenSSH\sftp.exe
```

If they are missing, install the Windows **OpenSSH Client** optional feature.

## 2. Generate a dedicated key

Open Command Prompt in the SongSync directory and run:

```bat
ssh-keygen -t ed25519 -f sftp_key -C "radioboss-songsync"
```

This creates:

```text
sftp_key
sftp_key.pub
```

For a fully unattended Windows upload, use a dedicated key without a
passphrase. A passphrase-protected key requires an SSH agent and cannot be
entered by a background RadioBOSS event.

If the server does not support Ed25519 keys, create an RSA key instead:

```bat
ssh-keygen -t rsa -b 4096 -f sftp_key -C "radioboss-songsync"
```

## 3. Add the public key to the server

The public key is the single line inside `sftp_key.pub`.

Add that complete line to:

```text
~/.ssh/authorized_keys
```

Important:

- Append the new line to an existing `authorized_keys` file.
- Do not replace or empty the file; it may already contain other valid keys.
- Use the hosting control panel when it provides an SSH-key function.
- If shell access is available, use permissions `700` for `.ssh` and `600` for
  `authorized_keys`.

Example server commands:

```sh
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

The SFTP account must also have write access to the public and private target
directories used by SongSync.

## 4. Protect the private key on Windows

Windows OpenSSH rejects a private key that can be read by other users. In
Command Prompt, from the SongSync directory, run:

```bat
icacls "sftp_key" /inheritance:r
icacls "sftp_key" /grant:r "%USERNAME%:(R)"
```

The key should remain readable only by the Windows account that runs SongSync.

## 5. Configure SongSync

Run `RadioBOSS-SongSync-Setup.exe`, open the SFTP step and enter:

```text
Private key file: sftp_key
Key passphrase:   leave empty for unattended use
Known hosts file: sftp_known_hosts
```

Leave the SFTP password empty when the key is the only authentication method.

Enter the exact server filesystem paths for the two destinations, for example:

```text
/path/to/songrequest/data/main/public
/path/to/songrequest/data/main/private
```

Then select **Test SFTP connection**.

## 6. First connection and server trust

FileZilla normally displays a dialog asking whether the server key should be
trusted. SongSync performs the same security step without an interactive
dialog because scheduled uploads cannot click **Yes**.

With **Trust server key on first successful connection** enabled:

1. The first successful connection records the server key in
   `sftp_known_hosts`.
2. Future connections require the same server key.
3. An unexpected server-key change stops the upload.

This is trust on first use (TOFU). Ideally, compare the server fingerprint with
the value published by the hosting provider before the first connection.

Do not delete `sftp_known_hosts` merely to bypass a warning. If the hosting
provider intentionally replaced the SSH server key, verify the new fingerprint
first and only then establish trust again.

## Optional manual connection test

From the SongSync directory:

```bat
sftp -i sftp_key -P 22 -o IdentitiesOnly=yes -o UserKnownHostsFile=sftp_known_hosts -o StrictHostKeyChecking=accept-new username@example-host
```

Replace the port, username and host. After the connection succeeds, enter:

```text
quit
```

The manual command and the SongSync setup test should use the same key and
known-hosts file.

## Troubleshooting

### `Bad permissions` or `UNPROTECTED PRIVATE KEY FILE`

The private key is readable by other Windows users. Repeat the `icacls`
commands above from the correct SongSync directory.

### `Permission denied (publickey)`

Check that:

- the correct public key was appended to `authorized_keys`;
- the private key matches that public key;
- the SFTP username is correct;
- the hosting account permits SSH-key authentication; and
- `.ssh` and `authorized_keys` have secure permissions.

### `Host key verification failed`

The stored server identity differs from the current server. Verify the host and
fingerprint with the provider before changing the known-hosts file.

### `Remote directory not found`

The path is missing or is a website URL instead of a server filesystem path.
Create the directories first and use the exact path visible to the SFTP
account.

### Password works in FileZilla but the SSH key does not

A successful password login proves that SFTP is enabled, but it does not prove
that the server accepts the uploaded public key. Check the hosting provider's
SSH-key instructions and the account's `authorized_keys` file.
