#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# Restore a Bookings MySQL backup from Cloudflare R2 to a target database.
#
# Required environment variables:
#   RESTORE_MYSQL_URL       mysql://user:pass@host:port/database
#   R2_ACCESS_KEY_ID
#   R2_SECRET_ACCESS_KEY
#   R2_ENDPOINT
#   R2_BUCKET
#
# Optional:
#   BACKUP_FILE             Specific backup key in R2 (e.g. bookings-backup-20260704-020000.sql.gz)
#                           If not set, the latest backup is downloaded automatically.
#
# Example:
#   RESTORE_MYSQL_URL="mysql://user:pass@localhost:3306/bookings_restore_test" \
#   R2_ACCESS_KEY_ID=... R2_SECRET_ACCESS_KEY=... R2_ENDPOINT=... R2_BUCKET=... \
#   ./scripts/restore-db.sh
# -----------------------------------------------------------------------------

set -euo pipefail

for var in RESTORE_MYSQL_URL R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY R2_ENDPOINT R2_BUCKET; do
    if [ -z "${!var:-}" ]; then
        echo "ERROR: $var is not set." >&2
        exit 1
    fi
done

# Configure AWS CLI for R2
aws configure set aws_access_key_id "$R2_ACCESS_KEY_ID" >/dev/null
aws configure set aws_secret_access_key "$R2_SECRET_ACCESS_KEY" >/dev/null
aws configure set default.region auto >/dev/null
aws configure set default.s3.signature_version s3v4 >/dev/null
aws configure set default.output json >/dev/null

# Determine which backup to restore
if [ -n "${BACKUP_FILE:-}" ]; then
    KEY="$BACKUP_FILE"
    echo "Using specified backup: $KEY"
else
    echo "Finding latest backup in s3://$R2_BUCKET/ ..."
    KEY=$(aws s3 ls "s3://$R2_BUCKET/" --endpoint-url "$R2_ENDPOINT" \
        | grep -E '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\s+[0-9]+\s+bookings-backup-[0-9]{8}-[0-9]{6}\.sql\.gz$' \
        | sort \
        | tail -n 1 \
        | awk '{print $4}')

    if [ -z "$KEY" ]; then
        echo "ERROR: No backup file found in s3://$R2_BUCKET/" >&2
        exit 1
    fi
    echo "Latest backup: $KEY"
fi

LOCAL_FILE=$(mktemp "bookings-backup-XXXXXX.sql.gz")
trap 'rm -f "$LOCAL_FILE"' EXIT

echo "Downloading s3://$R2_BUCKET/$KEY ..."
aws s3 cp "s3://$R2_BUCKET/$KEY" "$LOCAL_FILE" --endpoint-url "$R2_ENDPOINT"

# Parse target MySQL URL and write a temporary options file so the password
# does not appear in the process list.
CRED_FILE=$(mktemp)
trap 'rm -f "$LOCAL_FILE" "$CRED_FILE"' EXIT

DB_NAME=$(python3 - "$CRED_FILE" "$RESTORE_MYSQL_URL" <<'PY'
import sys
import urllib.parse

cred_file = sys.argv[1]
url = sys.argv[2]
parsed = urllib.parse.urlparse(url)

host = parsed.hostname or 'localhost'
port = parsed.port or 3306
user = urllib.parse.unquote(parsed.username or '')
password = urllib.parse.unquote(parsed.password or '')
database = urllib.parse.unquote(parsed.path.lstrip('/').split('?')[0])

if not all([host, user, database]):
    raise ValueError('RESTORE_MYSQL_URL is missing required components')

with open(cred_file, 'w') as f:
    f.write('[client]\n')
    f.write(f'host={host}\n')
    f.write(f'port={port}\n')
    f.write(f'user={user}\n')
    f.write(f'password={password}\n')
    f.write('ssl-mode=REQUIRED\n')

print(database)
PY
)

echo "Ensuring database exists: $DB_NAME"
mysql --defaults-extra-file="$CRED_FILE" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"

echo "Restoring backup into database: $DB_NAME"
gunzip -c "$LOCAL_FILE" | mysql --defaults-extra-file="$CRED_FILE" "$DB_NAME"

echo ""
echo "Restore completed. Row counts:"
echo "--------------------------------"
echo -n "ea_appointments:  "
mysql --defaults-extra-file="$CRED_FILE" "$DB_NAME" -N -e "SELECT COUNT(*) FROM ea_appointments;"

echo -n "ea_users:         "
mysql --defaults-extra-file="$CRED_FILE" "$DB_NAME" -N -e "SELECT COUNT(*) FROM ea_users;"

echo -n "ea_customers:     "
mysql --defaults-extra-file="$CRED_FILE" "$DB_NAME" -N -e "SELECT COUNT(*) FROM ea_users WHERE id_roles = (SELECT id FROM ea_roles WHERE slug = 'customer');" 2>/dev/null || echo "N/A (query requires role data)"

echo ""
echo "Restore finished successfully."
