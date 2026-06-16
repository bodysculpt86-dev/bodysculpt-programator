# -----------------------------------------------------------------------------
# Stage 1: Build the compiled CSS from SCSS.
#
# The repository ignores the generated .css/.min.css files in assets/css/, but
# the backend/frontend layouts reference them directly. We compile them here so
# the final Railway image contains the exact same stylesheets that work locally.
# -----------------------------------------------------------------------------
FROM node:20-slim AS assets-builder

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

COPY gulpfile.js ./
COPY assets/css ./assets/css
RUN npx gulp styles

# -----------------------------------------------------------------------------
# Stage 2: Final Easy!Appointments image.
# -----------------------------------------------------------------------------
FROM alextselegidis/easyappointments:1.5.2

# Copy local application changes (including security patches) over the upstream code.
# The base image serves the app from /var/www/html.
COPY ./application/ /var/www/html/application/

# Copy the freshly compiled stylesheets and themes. The upstream 1.5.2 image only
# keeps assets/css/layouts/ and assets/css/pages/, so these root CSS files must be
# supplied by the build stage above.
COPY --from=assets-builder /build/assets/css/backend.css /build/assets/css/backend.min.css /build/assets/css/frontend.css /build/assets/css/frontend.min.css /build/assets/css/general.css /build/assets/css/general.min.css /var/www/html/assets/css/
COPY --from=assets-builder /build/assets/css/themes/ /var/www/html/assets/css/themes/

# White-label JS strings (the base image would otherwise keep "Easy!Appointments").
COPY ./assets/js/app.js /var/www/html/assets/js/app.js
COPY ./assets/js/utils/calendar_sync.js /var/www/html/assets/js/utils/calendar_sync.js

# Calendar customizations (default/table views, visible hours, 24h format).
COPY ./assets/js/utils/calendar_default_view.js /var/www/html/assets/js/utils/calendar_default_view.js
COPY ./assets/js/utils/calendar_table_view.js /var/www/html/assets/js/utils/calendar_table_view.js

# Customer import/export feature (backend customers page).
COPY ./assets/js/http/customers_http_client.js /var/www/html/assets/js/http/customers_http_client.js
COPY ./assets/js/pages/customers.js /var/www/html/assets/js/pages/customers.js

# Timezone unification (remove per-user timezone selectors).
COPY ./assets/js/pages/account.js /var/www/html/assets/js/pages/account.js
COPY ./assets/js/pages/admins.js /var/www/html/assets/js/pages/admins.js
COPY ./assets/js/pages/booking.js /var/www/html/assets/js/pages/booking.js
COPY ./assets/js/pages/providers.js /var/www/html/assets/js/pages/providers.js
COPY ./assets/js/pages/secretaries.js /var/www/html/assets/js/pages/secretaries.js
COPY ./assets/js/http/booking_http_client.js /var/www/html/assets/js/http/booking_http_client.js
COPY ./assets/js/components/appointments_modal.js /var/www/html/assets/js/components/appointments_modal.js
COPY ./assets/js/utils/calendar_event_popover.js /var/www/html/assets/js/utils/calendar_event_popover.js

# Patched session driver (PHP 8.2 ReturnTypeWillChange fix)
COPY ./system/libraries/Session/drivers/Session_database_driver.php /var/www/html/system/libraries/Session/drivers/Session_database_driver.php

# Copy the Railway wrapper entrypoint that fixes the Apache MPM conflict
# while preserving the upstream image's config templating and startup.
COPY ./docker-entrypoint-railway.sh /usr/local/bin/docker-entrypoint-railway.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-railway.sh

# Use the wrapper as the container entrypoint.
ENTRYPOINT ["docker-entrypoint-railway.sh"]
