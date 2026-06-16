FROM alextselegidis/easyappointments:1.5.2

# Copy local application changes (including security patches) over the upstream code.
# The base image serves the app from /var/www/html.
COPY ./application/ /var/www/html/application/

# White-label JS strings (the base image would otherwise keep "Easy!Appointments").
COPY ./assets/js/app.js /var/www/html/assets/js/app.js
COPY ./assets/js/utils/calendar_sync.js /var/www/html/assets/js/utils/calendar_sync.js

# Calendar customizations (default/table views, visible hours, 24h format).
COPY ./assets/js/utils/calendar_default_view.js /var/www/html/assets/js/utils/calendar_default_view.js
COPY ./assets/js/utils/calendar_table_view.js /var/www/html/assets/js/utils/calendar_table_view.js

# Patched session driver (PHP 8.2 ReturnTypeWillChange fix)
COPY ./system/libraries/Session/drivers/Session_database_driver.php /var/www/html/system/libraries/Session/drivers/Session_database_driver.php

# Copy the Railway wrapper entrypoint that fixes the Apache MPM conflict
# while preserving the upstream image's config templating and startup.
COPY ./docker-entrypoint-railway.sh /usr/local/bin/docker-entrypoint-railway.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-railway.sh

# Use the wrapper as the container entrypoint.
ENTRYPOINT ["docker-entrypoint-railway.sh"]
