# Complete backup

Run from PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/backup_full.ps1
```

The generated archive in `backups/` contains:

- a fresh complete database dump, including credentials and admin hashes;
- the full updated project;
- `.env.local`, which is required to locate and authenticate to the database;
- legacy `storage` files;
- a manifest and a separate SHA-256 checksum.

The ZIP contains plaintext secrets and personal/payment data. Move it to
encrypted, access-controlled storage. Do not put it in Git or a public web
directory.

PHP sessions are intentionally not preserved. They are temporary login state
and should be invalidated during a restore.

On cPanel, use both **Backup / Backup Wizard** for the home directory and a
MySQL database backup. A complete cPanel account backup already includes both
when the hosting provider enables that feature.
