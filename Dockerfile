FROM alextselegidis/easyappointments:1.5.2

# Copy local application changes (including security patches) over the upstream code.
# The base image serves the app from /var/www/html.
COPY ./application/ /var/www/html/application/

# The base image already provides the Apache/PHP entrypoint and runtime configuration,
# so we keep CMD/ENTRYPOINT unchanged.
