#!/bin/bash
# ------------------------------------------------------------------------------
# Export the local Easy!Appointments MySQL database to a SQL dump.
# Run this from the project root (where docker-compose.yml lives).
# The resulting file can be imported into Railway's MySQL/MariaDB service
# via phpMyAdmin or the mysql CLI.
# ------------------------------------------------------------------------------

set -e

DUMP_DIR="storage/backups"
DUMP_FILE="$DUMP_DIR/easyappointments-local-$(date +%Y%m%d-%H%M%S).sql"

mkdir -p "$DUMP_DIR"

echo "Creating local database dump: $DUMP_FILE"
docker compose exec -T mysql mysqldump \
    --single-transaction \
    --no-tablespaces \
    --routines \
    --triggers \
    -u user \
    -ppassword \
    easyappointments > "$DUMP_FILE"

echo "Done. Dump size: $(du -h "$DUMP_FILE" | cut -f1)"
echo "Import it into Railway through phpMyAdmin (Import tab) or:"
echo "  mysql -h <RAILWAY_HOST> -u <RAILWAY_USER> -p<RAILWAY_PASS> <RAILWAY_DB> < $DUMP_FILE"
