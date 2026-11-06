#!/usr/bin/env bash
# =============================================================================
#  Update Situs CSIRT yang dipasang dengan scripts/install-ubuntu.sh
#
#     sudo bash /var/www/csirt/scripts/update-ubuntu.sh
#     sudo SOURCE_DIR=/path/kode-baru bash /var/www/csirt/scripts/update-ubuntu.sh   # tanpa git
#
#  Urutan: backup → mode pemeliharaan → ambil kode → dependensi → build aset →
#          migrasi → cache → reload PHP-FPM → keluar dari mode pemeliharaan.
# =============================================================================
set -Eeuo pipefail

APP_DIR=${APP_DIR:-/var/www/csirt}
APP_USER=csirt
WEB_USER=www-data
PHP_VERSION=8.5

step() { echo; echo $'\e[1;34m'"==> $*"$'\e[0m'; }
ok()   { echo $'\e[1;32m'"  ✓ $*"$'\e[0m'; }
die()  { echo $'\e[1;31m'"ERROR: $*"$'\e[0m' >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Jalankan sebagai root: sudo bash $0"
[[ -f "$APP_DIR/artisan" ]] || die "Aplikasi tidak ditemukan di $APP_DIR"
# Periksa sumber kode sebelum backup & mode pemeliharaan.
if [[ -n "${SOURCE_DIR:-}" ]]; then
    [[ -f "$SOURCE_DIR/artisan" ]] || die "SOURCE_DIR tidak berisi aplikasi: $SOURCE_DIR"
elif [[ ! -d "$APP_DIR/.git" ]]; then
    die "$APP_DIR bukan repo git (diinstal dari folder lokal). Jalankan dengan: sudo SOURCE_DIR=/path/kode-baru bash $0"
fi
cd "$APP_DIR"

artisan() { sudo -u "$WEB_USER" HOME=/tmp php artisan "$@"; }

# Pastikan situs keluar dari mode pemeliharaan walau update gagal di tengah jalan.
MAINTENANCE=0
cleanup() {
    if [[ $MAINTENANCE == 1 ]]; then
        echo $'\e[1;31m'"Update gagal. Situs dikembalikan online; periksa error di atas."$'\e[0m' >&2
        artisan up || true
    fi
}
trap cleanup EXIT

step "Backup sebelum update"
/usr/local/sbin/csirt-backup
ok "Backup tersimpan di /var/backups/csirt"

step "Mode pemeliharaan"
artisan down --retry=60
MAINTENANCE=1

step "Mengambil kode terbaru"
if [[ -n "${SOURCE_DIR:-}" ]]; then
    [[ -f "$SOURCE_DIR/artisan" ]] || die "SOURCE_DIR tidak berisi aplikasi: $SOURCE_DIR"
    rsync -a --delete --exclude .git --exclude vendor --exclude node_modules --exclude public/build \
        --exclude public/storage --exclude .env --exclude storage --exclude bootstrap/cache \
        "$SOURCE_DIR"/ "$APP_DIR"/
    chown -R "$APP_USER:$WEB_USER" "$APP_DIR"
    ok "Kode disalin dari $SOURCE_DIR"
else
    sudo -u "$APP_USER" -H git -C "$APP_DIR" pull --ff-only
    ok "git pull: $(sudo -u "$APP_USER" -H git -C "$APP_DIR" log --oneline -1)"
fi

step "Dependensi & aset"
sudo -u "$APP_USER" -H composer install --no-dev --optimize-autoloader --no-interaction --no-progress
sudo -u "$APP_USER" -H bash -c 'npm ci --no-audit --no-fund --loglevel=error && npm run build && rm -rf node_modules'
ok "Composer & build aset selesai"

step "Permission"
chown -R "$APP_USER:$WEB_USER" "$APP_DIR"
find "$APP_DIR" -path "$APP_DIR/storage" -prune -o -path "$APP_DIR/bootstrap/cache" -prune -o -type d -exec chmod 2750 {} +
find "$APP_DIR" -path "$APP_DIR/storage" -prune -o -path "$APP_DIR/bootstrap/cache" -prune -o -type f -exec chmod 0640 {} +
chmod 0750 "$APP_DIR/artisan"
chown -R "$WEB_USER:$WEB_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
ok "Permission dipulihkan"

step "Migrasi & cache"
artisan optimize:clear >/dev/null
artisan migrate --force
artisan optimize >/dev/null
# OPcache memakai validate_timestamps=0, jadi reload wajib agar kode baru terbaca.
systemctl reload "php${PHP_VERSION}-fpm"
ok "Migrasi selesai, cache dibuat ulang, PHP-FPM di-reload"

artisan up
MAINTENANCE=0
ok "Situs kembali online"
