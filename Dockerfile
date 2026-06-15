FROM alextselegidis/easyappointments:1.5.2

# Copy local application changes (including security patches) over the upstream code.
# The base image serves the app from /var/www/html.
COPY ./application/ /var/www/html/application/

# Patched session driver (PHP 8.2 ReturnTypeWillChange fix)
COPY ./system/libraries/Session/drivers/Session_database_driver.php /var/www/html/system/libraries/Session/drivers/Session_database_driver.php

# Copy the Railway wrapper entrypoint that fixes the Apache MPM conflict
# while preserving the upstream image's config templating and startup.
COPY ./docker-entrypoint-railway.sh /usr/local/bin/docker-entrypoint-railway.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-railway.sh

# Use the wrapper as the container entrypoint.
ENTRYPOINT ["docker-entrypoint-railway.sh"]
