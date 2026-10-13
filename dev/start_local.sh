#!/usr/bin/env bash
# Porneste mediul local: MariaDB (3306), Mailpit (SMTP 1025, interfata http://localhost:8025)
# si serverul PHP 8.1 (http://localhost:8080). Toate ascultă doar pe 127.0.0.1.
#
#   bash dev/start_local.sh
#
# Instalare (o singura data, vezi IMPLEMENTATION_PROGRESS.md → T1):
#   winget install PHP.PHP.NTS.8.1 axllent.mailpit  + MariaDB 10.11 portabil in %LOCALAPPDATA%\mugurel-dev
set -u
cd "$(dirname "$0")/.."
DEV=/c/Users/lucia/AppData/Local/mugurel-dev
PHP=/c/Users/lucia/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.NTS.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe
MAILPIT=/c/Users/lucia/AppData/Local/Microsoft/WinGet/Packages/axllent.mailpit_Microsoft.Winget.Source_8wekyb3d8bbwe/mailpit.exe

up() { curl -s -o /dev/null --max-time 1 "$1"; }

if ! "$DEV/mariadb/bin/mysqladmin.exe" -u root -h 127.0.0.1 ping >/dev/null 2>&1; then
    "$DEV/mariadb/bin/mysqld.exe" --defaults-file="C:/Users/lucia/AppData/Local/mugurel-dev/data/my.ini" >"$DEV/mysqld.log" 2>&1 &
    echo "MariaDB pornit"
fi
up http://127.0.0.1:8025 || { "$MAILPIT" --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025 >"$DEV/mailpit.log" 2>&1 & echo "Mailpit pornit"; }
up http://localhost:8080 || { "$PHP" -S localhost:8080 -t . dev/router_local.php >"$DEV/php-server.log" 2>&1 & echo "PHP pornit"; }
sleep 2
"$DEV/mariadb/bin/mysqladmin.exe" -u root -h 127.0.0.1 ping && up http://127.0.0.1:8025 && up http://localhost:8080 \
    && echo "Site: http://localhost:8080   Mailpit: http://localhost:8025"
