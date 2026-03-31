#!/bin/sh
set -eu

SSL_DIR="/etc/apache2/ssl"
STATE_FILE="/tmp/ssl-watch.state"

snapshot_ssl_state() {
    if [ ! -d "$SSL_DIR" ]; then
        echo "missing"
        return
    fi

    find "$SSL_DIR" -maxdepth 1 -type f -printf '%f|%T@|%s\n' 2>/dev/null | sort
}

watch_ssl_dir() {
    last_state="$(snapshot_ssl_state)"
    printf '%s' "$last_state" > "$STATE_FILE"

    while true; do
        sleep 2
        current_state="$(snapshot_ssl_state)"

        if [ "$current_state" != "$last_state" ]; then
            printf '%s' "$current_state" > "$STATE_FILE"
            last_state="$current_state"
            apache2ctl -k graceful >/proc/1/fd/1 2>/proc/1/fd/2 || true
        fi
    done
}

watch_ssl_dir &
exec apache2-foreground