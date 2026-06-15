#!/bin/bash

# -----------------------------------------------------------------------------
# Railway entrypoint wrapper for Easy!Appointments
#
# Fixes the classic "More than one MPM loaded" Apache error on Railway by
# disabling conflicting MPM modules and ensuring only mpm_prefork is active.
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

# Delegate to the original Easy!Appointments entrypoint, which templates
# config.php / email.php from environment variables and then runs
# apache2-foreground. Using exec preserves signal handling (PID 1).
exec /usr/local/bin/docker-entrypoint.sh "$@"
