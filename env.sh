# Source this file to get the userspace toolchain on PATH.
#   source env.sh

export PATH="$HOME/.local/bin:$PATH"

# Userspace PHP extensions (mbstring, curl, intl, xml, bcmath, gd, zip, mysqli)
export PHP_INI_SCAN_DIR="/etc/php/8.4/cli/conf.d:$HOME/.local/php/conf.d"

# Userspace MariaDB
export JUVO_DB_SOCKET="$HOME/.local/mariadb/run/mysqld.sock"
export JUVO_DB_PORT=3307
