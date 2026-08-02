#!/bin/bash

# -----------------------------------------------------------------------------
# Railway entrypoint wrapper for Easy!Appointments
#
# Fixes the classic "More than one MPM loaded" Apache error on Railway by
# disabling conflicting MPM modules and ensuring only mpm_prefork is active.
# Also teaches Apache/PHP to trust the X-Forwarded-Proto header from Railway's
# HTTPS proxy so that sessions and redirects work over HTTPS.
#
# The upstream entrypoint generates config.php / email.php from environment
# variables and then runs apache2-foreground. We replace that final
# apache2-foreground call with a wrapper that either:
#   - runs the configured RAILWAY_CRON_COMMAND and exits (cron mode), or
#   - starts Apache normally (web mode).
# -----------------------------------------------------------------------------

set -e

# Disable conflicting MPM modules (ignore errors if they are not loaded).
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true

# Ensure the prefork MPM is enabled.
a2enmod mpm_prefork 2>/dev/null || true

# Ensure mod_setenvif is available so Apache can read X-Forwarded-Proto.
a2enmod setenvif 2>/dev/null || true

# Ensure mod_rewrite is available for the pretty short payment links.
a2enmod rewrite 2>/dev/null || true

# Pretty short payment links: /pay/<slug> -> /index.php/pay/<slug>
# Allows WhatsApp payment links like https://bodysculpt.ro/pay/abc123XY
# while the rest of the app keeps its index.php URLs unchanged.
cat <<'CONF' >/etc/apache2/conf-enabled/pay-short-links.conf
RewriteEngine On
RewriteRule ^/pay/([A-Za-z0-9]{1,16})/?$ /index.php/pay/$1 [L]
CONF

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

# Install a wrapper script that the upstream entrypoint will call instead of
# apache2-foreground. The wrapper decides whether to run Apache (normal web
# service) or the Railway cron command (one-off cron container).
cat <<'WRAPPER' >/usr/local/bin/docker-apache-or-cron.sh
#!/bin/bash
set -e

# Run pending database migrations (safe: failures are logged but do not stop startup).
php /var/www/html/index.php console migrate 2>&1 || echo "Automatic migrations failed; continuing startup." >&2

# Bootstrap the super-admin account from Railway env vars (idempotent).
php /var/www/html/index.php console bootstrap 2>&1 || echo "Super-admin bootstrap failed; continuing startup." >&2

# Railway cron mode: when RAILWAY_CRON_COMMAND is set, run it and exit.
# This must happen BEFORE Apache starts, so cron containers never launch the web server.
if [ -n "${RAILWAY_CRON_COMMAND}" ]; then
    echo "Running cron command: ${RAILWAY_CRON_COMMAND}"
    exec ${RAILWAY_CRON_COMMAND}
fi

# Web mode: start Apache in the foreground as usual.
exec apache2-foreground
WRAPPER
chmod +x /usr/local/bin/docker-apache-or-cron.sh

# Patch the upstream entrypoint to call our wrapper instead of apache2-foreground.
# We match the whole line to avoid partial replacements.
sed -i 's/^[[:space:]]*apache2-foreground[[:space:]]*$/docker-apache-or-cron.sh/' /usr/local/bin/docker-entrypoint.sh

# Delegate to the patched upstream entrypoint, which generates config.php / email.php
# from environment variables and then invokes docker-apache-or-cron.sh.
# Using exec preserves signal handling (PID 1).
exec /usr/local/bin/docker-entrypoint.sh "$@"
