#!/bin/sh
# One image, two jobs. Which one a container does is its first argument:
#
#   serve    the application, on Octane                        (the default)
#   release  what has to happen once before a new version serves
#
# Anything else is run as given, so `docker compose run --rm app php artisan …`
# works for one-off commands.
set -eu

case "${1:-serve}" in
    serve)
        # Configuration, routes, views and events are compiled here and not
        # while building the image: they are read from the environment, and
        # the environment is only known once the container starts.
        php artisan optimize

        exec php artisan octane:frankenphp \
            --host=0.0.0.0 \
            --port="${OCTANE_PORT:-8000}" \
            --workers="${OCTANE_WORKERS:-auto}" \
            --max-requests="${OCTANE_MAX_REQUESTS:-1000}" \
            --caddyfile=/app/docker/Caddyfile \
            --log-level="${OCTANE_LOG_LEVEL:-warn}"
        ;;

    release)
        # --isolated takes a lock in the cache, so two releases started at the
        # same moment cannot both migrate.
        php artisan migrate --force --isolated

        # Roles and permissions are data the application cannot run without.
        # Safe on every release: a role that already exists keeps whatever was
        # changed on the Peran & Izin screen.
        exec php artisan db:seed --class=RolePermissionSeeder --force
        ;;

    *)
        exec "$@"
        ;;
esac
