#!/bin/bash
set -e

# Start Redis server in background
redis-server --daemonize yes

# Start PHP-FPM in background
php-fpm -D

# Start nginx in foreground
nginx -g 'daemon off;'
