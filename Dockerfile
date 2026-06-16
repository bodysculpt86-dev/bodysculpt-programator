FROM alextselegidis/easyappointments:1.5.2

# Copy local application changes (including security patches) over the upstream code.
# The base image serves the app from /var/www/html.
COPY ./application/ /var/www/html/application/

# Local CSS files are referenced by our views as backend.css / frontend.css / general.css
# but the upstream 1.5.2 image only keeps layouts/ and pages/ subdirectories. Copy our
# local stylesheets and themes so the backend/frontend layouts render correctly on Railway.
COPY ./assets/css/backend.css ./assets/css/backend.min.css ./assets/css/frontend.css ./assets/css/frontend.min.css ./assets/css/general.css ./assets/css/general.min.css /var/www/html/assets/css/
COPY ./assets/css/themes/ /var/www/html/assets/css/themes/

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
