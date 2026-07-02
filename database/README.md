# Database migration package

This directory contains a non-destructive MySQL/MariaDB design and a snapshot
of the application's live file-based state.

## Import

Back up the project directory and database first, then run:

```powershell
mysql -u root -p -e "CREATE DATABASE simple_event_register CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p simple_event_register < database/schema.sql
mysql -u root -p simple_event_register < database/current_data.sql
php database/seed_secrets.php
```

The application uses the database through `db.php`. Existing CSV/JSON files
remain untouched as a rollback backup, but new registrations, payments,
settings, credentials, admin authentication, archives, exports, audit events,
and notification logs are database-backed.

`current_data.sql` is idempotent for settings and prices. The tracking counter
uses `GREATEST`, so rerunning the snapshot cannot move it backwards.

## Current snapshot

At capture time there were no participant CSVs and no pending or completed
payment JSON records. The current event window, four ticket prices, tracking
counter, and non-secret notification settings are preserved.

API tokens, gateway credentials, and admin passwords are intentionally not
duplicated into SQL. Configure the environment keys listed in
`data_inventory.json`. Rotate the SMS and Telegram tokens that were previously
stored in PHP source files.

The current credential values are preserved in the project-root `.env.local`
file. Apache blocks dotfiles through the project `.htaccess`, and `.gitignore`
excludes the file from source control. Include `.env.local` in an encrypted,
access-controlled backup.

## cPanel

Create the database and user with cPanel's **MySQL Databases** tool, assign the
user all privileges, import `schema.sql` and `current_data.sql` in phpMyAdmin,
then set the cPanel database credentials in `.env.local`. Run
`database/seed_secrets.php` from cPanel Terminal once, then remove public access
to that script or delete it after successful deployment.
