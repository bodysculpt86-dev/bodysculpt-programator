# BookingS — Automated MySQL Backup to Cloudflare R2

This repository backs up the production MySQL database (hosted on Railway) daily to a Cloudflare R2 bucket using GitHub Actions.

## Secrets required

Set these in **Settings → Secrets and variables → Actions**:

| Secret | Description |
|---|---|
| `MYSQL_PUBLIC_URL` | Public MySQL connection URL from Railway (e.g. `mysql://user:pass@host:3306/easyappointments`) |
| `R2_ACCESS_KEY_ID` | Cloudflare R2 access key ID |
| `R2_SECRET_ACCESS_KEY` | Cloudflare R2 secret access key |
| `R2_ENDPOINT` | R2 S3 endpoint, e.g. `https://<account-id>.r2.cloudflarestorage.com` |
| `R2_BUCKET` | Name of the R2 bucket (e.g. `bodysculpt-backups`) |

`R2_ACCOUNT_ID` is not used directly because the account ID is already part of `R2_ENDPOINT`.

## Workflows

### `backup-db.yml`

Runs every day at **02:00 UTC** and can be triggered manually via `workflow_dispatch`.

What it does:

1. Connects to Railway MySQL using `MYSQL_PUBLIC_URL`.
2. Runs `mysqldump` with:
   - `--single-transaction`
   - `--routines`
   - `--triggers`
   - `--no-tablespaces`
   - `--ssl-mode=REQUIRED`
   - `--set-gtid-purged=OFF`
3. Compresses the dump to `bookings-backup-YYYYMMDD-HHMMSS.sql.gz`.
4. Fails the workflow if the dump is smaller than 1 KB (empty or corrupted).
5. Uploads the backup to the configured R2 bucket using AWS CLI v2.
6. Applies retention:
   - keep daily backups for 30 days
   - keep monthly backups (1st of month) for 12 months
   - delete older backups automatically

### `test-restore.yml`

Manual workflow used to verify that a backup can actually be restored.

It downloads the latest backup (or a specific one if provided) and restores it to a database named `<original_db>_restore_test` (e.g. `easyappointments_restore_test`). After restore it prints row counts for `ea_appointments`, `ea_users` and customers.

Run it from **Actions → Test Restore from R2 → Run workflow**.

## Local restore script

`scripts/restore-db.sh` restores a backup from R2 to any MySQL database.

Example:

```bash
RESTORE_MYSQL_URL="mysql://user:pass@localhost:3306/bookings_restore_test" \
R2_ACCESS_KEY_ID=... \
R2_SECRET_ACCESS_KEY=... \
R2_ENDPOINT=... \
R2_BUCKET=bodysculpt-backups \
./scripts/restore-db.sh
```

To restore a specific backup instead of the latest one:

```bash
BACKUP_FILE="bookings-backup-20260704-020000.sql.gz" \
RESTORE_MYSQL_URL=... \
... ./scripts/restore-db.sh
```

The script will:

1. Download the backup from R2.
2. Create the target database if it does not exist.
3. Restore the dump.
4. Print row counts for appointments and users/customers.

## Retention policy

Implemented in `.github/scripts/prune-r2-backups.py`.

- **Daily backups:** kept for 30 days.
- **Monthly backups:** created on the 1st of each month, kept for 12 months.
- **Older backups:** deleted automatically to avoid unlimited storage growth.

## Testing the setup

1. Push the workflows to the default branch.
2. Set the required secrets in GitHub.
3. Go to **Actions → Daily MySQL Backup to Cloudflare R2 → Run workflow**.
4. Check the R2 bucket for the new `.sql.gz` file.
5. Go to **Actions → Test Restore from R2 → Run workflow** to verify restore works.

A backup that has not been restored is not a backup — run the restore test at least once after setup.
