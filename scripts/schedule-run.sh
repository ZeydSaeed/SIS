#!/usr/bin/env bash
# Run Laravel scheduler every minute (Linux/macOS cron)
# Cron: * * * * * /path/to/sis/scripts/schedule-run.sh >> /dev/null 2>&1

cd "$(dirname "$0")/.." || exit 1
php artisan schedule:run --verbose
