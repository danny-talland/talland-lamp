#!/bin/sh
set -eu

SSL_DIR="/etc/apache2/ssl"
VHOSTS_DIR="/etc/apache2/sites-enabled"
DEFAULT_VHOST_FILE="$VHOSTS_DIR/000-tlamp-dashboard.conf"
STATE_FILE="/tmp/apache-watch.state"

ensure_default_vhost() {
    mkdir -p "$VHOSTS_DIR"

    if [ -f "$DEFAULT_VHOST_FILE" ]; then
        return
    fi

    cat > "$DEFAULT_VHOST_FILE" <<'CONF'
# TLAMP_DEFAULT_VHOST
<VirtualHost *:80>
    ServerName localhost
    ServerAlias 127.0.0.1 ::1
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/dashboard-error.log
    CustomLog ${APACHE_LOG_DIR}/dashboard-access.log combined
</VirtualHost>
CONF
}

snapshot_dir_state() {
    dir="$1"

    if [ ! -d "$dir" ]; then
        echo "missing:$dir"
        return
    fi

    find "$dir" -maxdepth 1 -type f -printf '%f|%T@|%s\n' 2>/dev/null | sort
}

snapshot_apache_state() {
    {
        snapshot_dir_state "$SSL_DIR"
        snapshot_dir_state "$VHOSTS_DIR"
    } | sort
}

watch_apache_dirs() {
    ensure_default_vhost
    last_state="$(snapshot_apache_state)"
    printf '%s' "$last_state" > "$STATE_FILE"

    while true; do
        sleep 2
        ensure_default_vhost
        current_state="$(snapshot_apache_state)"

        if [ "$current_state" != "$last_state" ]; then
            printf '%s' "$current_state" > "$STATE_FILE"
            last_state="$current_state"
            apache2ctl -k graceful >/proc/1/fd/1 2>/proc/1/fd/2 || true
        fi
    done
}

watch_apache_dirs &
exec apache2-foreground
