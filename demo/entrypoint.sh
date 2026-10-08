#!/bin/sh
# Supertext Magento demo. The container keeps no files between deploys (Railway allows
# only a few volumes per project): the shop lives in MySQL (DATABASE_URL), and so does
# app/etc/env.php. The search index lives in Elasticsearch (ELASTICSEARCH_HOST) and is
# rebuilt on every start.
#
# First start (empty database): installs Magento with a throwaway installer account.
# Every start: restores env.php, points the shop at its public address, applies module
# updates (setup:upgrade), then demo/setup.php creates missing store views, sample content,
# the editor role and the demo accounts (it never changes existing ones).
set -e

M=/var/www/magento
cd "$M"
magento() { runuser -u www-data -- php -d memory_limit=-1 bin/magento "$@"; }

# mod_php needs the prefork MPM. On Railway a second MPM can end up enabled and Apache
# refuses to start ("More than one MPM loaded"), so keep only prefork.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod -q mpm_prefork

# Apache listens on Railway's $PORT (default 80).
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

DB=$(php /opt/demo/state.php db)
DB_HOST=$(echo "$DB" | cut -d' ' -f1)
DB_PORT=$(echo "$DB" | cut -d' ' -f2)
DB_USER=$(echo "$DB" | cut -d' ' -f3)
DB_NAME=$(echo "$DB" | cut -d' ' -f4)
DOMAIN="${MAGENTO_DOMAIN:-${RAILWAY_PUBLIC_DOMAIN:-localhost}}"
SCHEME=https SECURE=1
case "$DOMAIN" in localhost*|127.*) SCHEME=http SECURE=0 ;; esac
BASE_URL="$SCHEME://$DOMAIN/"
ES_HOST="${ELASTICSEARCH_HOST:-elasticsearch.railway.internal}"
ES_PORT="${ELASTICSEARCH_PORT:-9200}"
ES_PREFIX="${ELASTICSEARCH_INDEX_PREFIX:-supertext_magento_demo}"

chown -R www-data:www-data "$M/app/etc" "$M/var" "$M/generated" "$M/pub/static" "$M/pub/media"

if php /opt/demo/state.php get > "$M/app/etc/env.php" 2>/dev/null; then
  echo "Magento configuration restored from the database."
  chown www-data:www-data "$M/app/etc/env.php"
  magento setup:upgrade --keep-generated --no-interaction
else
  rm -f "$M/app/etc/env.php"
  echo "First start: installing Magento (this takes a few minutes)..."
  # The installer insists on an admin. It gets a random name and password that are never
  # shown; demo/setup.php deletes it (any @supertext-demo.invalid account) as soon as the
  # DEMO_ADMIN account exists.
  INSTALLER="installer-$(head -c 6 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  INSTALLER_PASSWORD="$(head -c 24 /dev/urandom | base64 | tr -d '\n/+=')a1"
  SECURE_OPTIONS=""
  [ "$SECURE" = 1 ] && SECURE_OPTIONS="--base-url-secure=$BASE_URL --use-secure=1 --use-secure-admin=1"
  DB_PASS=$(php -r '$u = parse_url(getenv("DATABASE_URL")); echo rawurldecode($u["pass"] ?? "");')
  magento setup:install --no-interaction \
    --base-url="$BASE_URL" ${SECURE_OPTIONS} \
    --db-host="$DB_HOST:$DB_PORT" --db-name="$DB_NAME" --db-user="$DB_USER" --db-password="$DB_PASS" \
    --search-engine=elasticsearch8 --elasticsearch-host="$ES_HOST" --elasticsearch-port="$ES_PORT" \
    --elasticsearch-index-prefix="$ES_PREFIX" \
    --admin-firstname=Installer --admin-lastname=Account --admin-user="$INSTALLER" \
    --admin-email="$INSTALLER@supertext-demo.invalid" --admin-password="$INSTALLER_PASSWORD" \
    --language=en_US --currency=CHF --timezone=Europe/Zurich --use-rewrites=1 --backend-frontname=admin
  unset INSTALLER_PASSWORD DB_PASS
  php /opt/demo/state.php put < "$M/app/etc/env.php"
  echo "Magento installed."
fi

# The public address and the search engine can change between deploys.
magento config:set web/unsecure/base_url "$BASE_URL" >/dev/null
magento config:set web/secure/base_url "$BASE_URL" >/dev/null
magento config:set web/secure/use_in_frontend "$SECURE" >/dev/null
magento config:set web/secure/use_in_adminhtml "$SECURE" >/dev/null
magento config:set catalog/search/elasticsearch8_server_hostname "$ES_HOST" >/dev/null
magento config:set catalog/search/elasticsearch8_server_port "$ES_PORT" >/dev/null
magento config:set catalog/search/elasticsearch8_index_prefix "$ES_PREFIX" >/dev/null
# Demo convenience: no admin captcha; admin passwords don't expire.
magento config:set admin/captcha/enable 0 >/dev/null
magento config:set admin/security/password_lifetime "" >/dev/null || true
magento config:set admin/security/password_is_forced 0 >/dev/null

# Store views, sample content, editor role and demo accounts (DEMO_ADMIN_*, DEMO_EDITOR_*).
# Passwords are read from the environment inside the script; they never appear in the log.
runuser -u www-data --preserve-environment -- php -d memory_limit=-1 /opt/demo/setup.php "$M"

# The search index lives in Elasticsearch, which keeps no files either.
magento indexer:reindex >/dev/null && echo "Indexes rebuilt."
magento cache:flush >/dev/null
chown -R www-data:www-data "$M/var" "$M/generated" "$M/pub/static"
exec "$@"
