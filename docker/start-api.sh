#!/bin/sh
set -eu

# The database service is health-checked by Compose before this starts.
# Existing create_tables.php is CLI-only and uses CREATE TABLE IF NOT EXISTS.
if [ -f /var/www/html/database/create_tables.php ]; then
  echo 'Ensuring MDA database tables exist...'
  php /var/www/html/database/create_tables.php
else
  echo 'ERROR: database/create_tables.php is missing.' >&2
  exit 1
fi

exec apache2-foreground
