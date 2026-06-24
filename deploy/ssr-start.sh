#!/usr/bin/env bash
# Inertia SSR daemon launcher — NVM node ni PATH ga qo'shadi va artisan start-ssr ni ishga tushadi.
set -e

export NVM_DIR="${NVM_DIR:-/var/www/.nvm}"

if [ -s "$NVM_DIR/nvm.sh" ]; then
    # shellcheck disable=SC1091
    . "$NVM_DIR/nvm.sh"
fi

exec php /var/www/saleflow/artisan inertia:start-ssr
