#!/usr/bin/env bash
# Back-up van de online database en de plaatjes. Draai op de server vanuit de projectmap:
#   bash deploy/backup.sh               database + plaatjes (elke nacht via cron)
#   bash deploy/backup.sh --db-only     alleen de database (deploy.sh doet dit vóór elke migrate)
#
# De inloggegevens komen uit .env. Back-ups ouder dan KEEP_DAYS dagen worden opgeruimd.
set -euo pipefail

cd "$(dirname "$0")/.."

BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/myfitnessapp}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y-%m-%d_%H%M)"
DB_ONLY=false
[[ "${1:-}" == "--db-only" ]] && DB_ONLY=true

# Waarde uit .env, zonder aanhalingstekens
env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# Wachtwoord via een tijdelijk bestand, zodat het niet in de proceslijst staat
CREDENTIALS="$(mktemp)"
trap 'rm -f "$CREDENTIALS"' EXIT
chmod 600 "$CREDENTIALS"
cat > "$CREDENTIALS" <<CNF
[client]
host=$(env_value DB_HOST)
port=$(env_value DB_PORT)
user=$(env_value DB_USERNAME)
password=$(env_value DB_PASSWORD)
CNF

DATABASE="$(env_value DB_DATABASE)"
DUMP="$BACKUP_DIR/db-$STAMP.sql.gz"

echo "→ Database $DATABASE"
mysqldump --defaults-extra-file="$CREDENTIALS" --single-transaction --quick --no-tablespaces "$DATABASE" | gzip > "$DUMP.tmp"
mv "$DUMP.tmp" "$DUMP"
echo "  $DUMP ($(du -h "$DUMP" | cut -f1))"

if [[ "$DB_ONLY" == false ]]; then
    IMAGES="$BACKUP_DIR/images-$STAMP.tar.gz"
    echo "→ Plaatjes"
    tar -czf "$IMAGES.tmp" --exclude=livewire-tmp -C storage/app public
    mv "$IMAGES.tmp" "$IMAGES"
    echo "  $IMAGES ($(du -h "$IMAGES" | cut -f1))"
fi

echo "→ Ouder dan $KEEP_DAYS dagen opruimen"
find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'db-*.sql.gz' -o -name 'images-*.tar.gz' \) -mtime +"$KEEP_DAYS" -print -delete

echo "✓ Back-up klaar"
