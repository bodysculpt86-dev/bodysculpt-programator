#!/bin/bash

# -----------------------------------------------------------------------------
# Railway entrypoint wrapper for Easy!Appointments
#
# Fixes the classic "More than one MPM loaded" Apache error on Railway by
# disabling conflicting MPM modules and ensuring only mpm_prefork is active.
# Also teaches Apache/PHP to trust the X-Forwarded-Proto header from Railway's
# HTTPS proxy so that sessions and redirects work over HTTPS.
# After that, control is handed back to the upstream image's entrypoint so
# that the original environment-variable templating and Apache startup are
# preserved.
# -----------------------------------------------------------------------------

set -e

# Disable conflicting MPM modules (ignore errors if they are not loaded).
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true

# Ensure the prefork MPM is enabled.
a2enmod mpm_prefork 2>/dev/null || true

# Ensure mod_setenvif is available so Apache can read X-Forwarded-Proto.
a2enmod setenvif 2>/dev/null || true

# Configure Apache to trust Railway's X-Forwarded-Proto header and set HTTPS=on
# when the request arrived over HTTPS. This makes PHP see $_SERVER['HTTPS']
# correctly without modifying any application code.
cat <<'CONF' >/etc/apache2/conf-enabled/railway-forwarded-https.conf
# Trust X-Forwarded-Proto header from Railway's TLS-terminating proxy
# and expose it to PHP as the HTTPS environment variable.
SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on
CONF

# Replace the upstream product name with the white-label brand in the generated
# email config (and any other upstream templates that contain it).
sed -i 's/Easy!Appointments/Bookings by Revclar/g' /usr/local/bin/docker-entrypoint.sh

# Inject automatic migration execution into the upstream entrypoint right before
# Apache starts. The upstream entrypoint generates config.php/email.php first,
# so migrations can connect to the database. Failures are logged but do not stop
# startup.
#
# When RAILWAY_CRON_COMMAND is set, the container runs that command and exits
# instead of starting Apache. This lets the same image be used for Railway
# cron-job services (e.g. hourly SMS reminders).
perl -0pi -e 's/# Start Apache\n\napache2-foreground/# Start Apache\n\n# Run pending database migrations (safe: failures are logged but do not stop startup).\nphp \/var\/www\/html\/index.php console migrate 2>\&1 || echo "Automatic migrations failed; continuing startup." >\&2\n\n# Railway cron mode: run the configured one-off command and exit.\nif [ -n "${RAILWAY_CRON_COMMAND}" ]; then\n    echo "Running cron command: ${RAILWAY_CRON_COMMAND}"\n    exec ${RAILWAY_CRON_COMMAND}\nfi\n\napache2-foreground/' /usr/local/bin/docker-entrypoint.sh

# Delegate to the original Easy!Appointments entrypoint, which templates
# config.php / email.php from environment variables and then runs
# apache2-foreground (or RAILWAY_CRON_COMMAND if set). Using exec preserves
# signal handling (PID 1).
exec /usr/local/bin/docker-entrypoint.sh "$@"
