#!/bin/bash
# OpenSales POS — ishga tushirish (ikki marta bosing).
# PHP o'rnatish shart emas — ichida keladi.
cd "$(dirname "$0")"

PHP="./runtime/mac/php"
[ -x "$PHP" ] || PHP="php"
PORT=8090

# Birinchi marta: baza tayyorlash
if [ ! -f "storage/.installed" ]; then
  "$PHP" artisan migrate --force --no-interaction >/dev/null 2>&1
  "$PHP" artisan db:seed --force --no-interaction >/dev/null 2>&1
  touch "storage/.installed"
fi

# Server (agar band bo'lsa, boshqa portni sinaydi)
"$PHP" artisan serve --host=127.0.0.1 --port=$PORT >/dev/null 2>&1 &
SVPID=$!
sleep 2
open "http://127.0.0.1:$PORT"

echo "════════════════════════════════════════"
echo "  OpenSales POS ishlamoqda"
echo "  Brauzer: http://127.0.0.1:$PORT"
echo "  Login: kassa / kassa"
echo "  To'xtatish: bu oynani yoping"
echo "════════════════════════════════════════"
wait $SVPID
