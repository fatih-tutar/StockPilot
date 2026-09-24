#!/bin/bash
# Optional scheduler service start command.
set -e

while true
do
    php artisan schedule:run --verbose --no-interaction &
    sleep 60
done
