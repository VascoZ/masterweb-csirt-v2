#!/usr/bin/env bash
# =============================================================================
#  Instalasi lengkap Situs CSIRT (on-premise) di Ubuntu Server 22.04 / 24.04 LTS
#
#  Memasang & mengonfigurasi: Nginx, PHP-FPM 8.3, MariaDB, Composer, Node.js
#  (hanya untuk build aset), aplikasi CSIRT, TLS, firewall (UFW), fail2ban,
#  pembaruan keamanan otomatis, sinkronisasi waktu, dan backup harian.
#
#  Pemakaian (sebagai root):
#     sudo bash scripts/install-ubuntu.sh                 # interaktif
#     sudo DOMAIN=csirt.contoh.go.id ADMIN_EMAIL=... ADMIN_PASSWORD=... \
#          TLS_MODE=letsencrypt LE_EMAIL=... NONINTERACTIVE=1 bash scripts/install-ubuntu.sh
#
#  Variabel (opsional, akan ditanyakan bila kosong & mode interaktif):
#     DOMAIN            Nama domain situs, mis. csirt.contoh.go.id
#     SITE_NAME         Nama tim, mis. "Contoh-CSIRT"
#     ADMIN_NAME        Nama superadmin                   (default: Administrator)
#     ADMIN_USERNAME    Username superadmin               (default: admin)
#     ADMIN_EMAIL       Email superadmin
#     ADMIN_PASSWORD    Password superadmin (min. 8, huruf besar/kecil, angka, simbol)
#     TLS_MODE          letsencrypt | custom | selfsigned | none   (default: letsencrypt)
#     LE_EMAIL          Email untuk Let's Encrypt         (TLS_MODE=letsencrypt)
#     CERT_FILE         Path fullchain sertifikat         (TLS_MODE=custom)
#     KEY_FILE          Path private key                  (TLS_MODE=custom)
#     LOGIN_WHITELIST   IP/CIDR yang boleh membuka halaman login
#                       (default: jaringan privat 10/8, 172.16/12, 192.168/16)
#     SSH_ALLOW         IP/CIDR yang boleh SSH (default: semua, TIDAK disarankan)
#     REPO_URL          Sumber kode git  (default: https://github.com/VascoZ/masterweb-csirt-v2.git)
#     REPO_BRANCH       Branch git       (default: main)
#     SOURCE_DIR        Pakai folder lokal sebagai sumber kode (server tanpa internet ke GitHub)
#     APP_DIR           Lokasi aplikasi  (default: /var/www/csirt)
#     MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_ENCRYPTION
#     TEAM_EMAIL        Email tim penerima notifikasi aduan
#     TICKET_PREFIX     Prefix nomor tiket (default: CSIRT)
#     BACKUP_KEEP_DAYS  Lama penyimpanan backup harian (default: 14)
#     SKIP_FIREWALL=1   Lewati konfigurasi UFW (mis. bila firewall dikelola perangkat lain)
#     NONINTERACTIVE=1  Jangan bertanya; gagal bila ada variabel wajib yang kosong
#
#  Aman dijalankan ulang: database, APP_KEY, dan superadmin yang sudah ada tidak ditimpa.
#  Untuk memperbarui aplikasi gunakan scripts/update-ubuntu.sh.
# =============================================================================
set -Eeuo pipefail

LOG_FILE=/var/log/csirt-install.log
PHP_VERSION=8.3
NODE_MAJOR=22
APP_USER=csirt
WEB_USER=www-data
DB_NAME=csirt
DB_USER=csirt_app
FPM_SOCKET=/run/php/php${PHP_VERSION}-fpm-csirt.sock
INFO_FILE=/root/csirt-install-info.txt

# ----------------------------------------------------------------------------- util
c_reset=$'\e[0m'; c_blue=$'\e[1;34m'; c_green=$'\e[1;32m'; c_yellow=$'\e[1;33m'; c_red=$'\e[1;31m'
step()  { echo; echo "${c_blue}==> $*${c_reset}"; }
ok()    { echo "${c_green}  ✓ $*${c_reset}"; }
warn()  { echo "${c_yellow}  ! $*${c_reset}"; WARNINGS+=("$*"); }
die()   { echo "${c_red}ERROR: $*${c_reset}" >&2; exit 1; }
WARNINGS=()

trap 'echo "${c_red}Instalasi gagal di baris $LINENO (perintah: $BASH_COMMAND). Lihat $LOG_FILE${c_reset}" >&2' ERR

ask() { # ask VAR "Pertanyaan" "default" [secret]
    local var=$1 prompt=$2 default=${3:-} secret=${4:-} value
    if [[ -n "${!var:-}" ]]; then return; fi
    if [[ "${NONINTERACTIVE:-0}" == 1 ]]; then
        [[ -n "$default" ]] && { printf -v "$var" '%s' "$default"; return; }
        die "Variabel $var wajib diisi pada mode NONINTERACTIVE."
    fi
    while true; do
        if [[ -n "$secret" ]]; then
            read -r -s -p "  $prompt: " value </dev/tty; echo
        else
            read -r -p "  $prompt${default:+ [$default]}: " value </dev/tty
        fi
        value=${value:-$default}
        [[ -n "$value" ]] && break
        echo "  (wajib diisi)"
    done
    printf -v "$var" '%s' "$value"
}

run_as() { local user=$1; shift; sudo -u "$user" -H "$@"; }
artisan() { (cd "$APP_DIR" && sudo -u "$WEB_USER" HOME=/tmp php artisan "$@"); }

# Ubah/tambah KEY=VALUE pada .env dengan quoting yang aman untuk phpdotenv.
set_env() {
    php -r '
        [$_, $file, $key, $value] = $argv;
        if ($value === "" || preg_match("/^[A-Za-z0-9_.,:\/@+\-]+$/", $value)) {
            $quoted = $value;
        } elseif (! str_contains($value, "\x27")) {
            $quoted = "\x27" . $value . "\x27";               // kutip tunggal: literal
        } else {
            $quoted = "\"" . addcslashes($value, "\"\\") . "\"";
        }
        $line = $key . "=" . $quoted;
        $content = file_get_contents($file);
        $pattern = "/^" . preg_quote($key, "/") . "=.*$/m";
        $content = preg_match($pattern, $content)
            ? preg_replace($pattern, addcslashes($line, "\\$"), $content)
            : rtrim($content) . "\n" . $line . "\n";
        file_put_contents($file, $content);
    ' "$ENV_FILE" "$1" "$2"
}
get_env() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | sed -E "s/^['\"](.*)['\"]$/\1/"; }

# ----------------------------------------------------------------------------- 0. prasyarat
[[ $EUID -eq 0 ]] || die "Jalankan sebagai root: sudo bash $0"
mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$LOG_FILE") 2>&1
echo "===== Instalasi CSIRT dimulai $(date -Is) ====="

. /etc/os-release
[[ "${ID:-}" == ubuntu ]] || die "Script ini hanya untuk Ubuntu (terdeteksi: ${ID:-?})."
case "${VERSION_ID:-}" in
    22.04|24.04) ;;
    *) die "Versi Ubuntu ${VERSION_ID:-?} belum didukung. Gunakan 22.04 atau 24.04 LTS." ;;
esac
export DEBIAN_FRONTEND=noninteractive

step "Konfigurasi instalasi"
ask DOMAIN "Domain situs (mis. csirt.contoh.go.id)"
ask SITE_NAME "Nama tim CSIRT" "Contoh-CSIRT"
ADMIN_NAME=${ADMIN_NAME:-Administrator}
ADMIN_USERNAME=${ADMIN_USERNAME:-admin}
ask TLS_MODE "Mode TLS (letsencrypt/custom/selfsigned/none)" "letsencrypt"
case "$TLS_MODE" in
    letsencrypt) ask LE_EMAIL "Email untuk Let's Encrypt" ;;
    custom) ask CERT_FILE "Path sertifikat (fullchain .crt/.pem)"; ask KEY_FILE "Path private key (.key)"
            [[ -r "$CERT_FILE" && -r "$KEY_FILE" ]] || die "CERT_FILE/KEY_FILE tidak dapat dibaca." ;;
    selfsigned|none) ;;
    *) die "TLS_MODE tidak dikenal: $TLS_MODE" ;;
esac
ask LOGIN_WHITELIST "IP/CIDR yang boleh membuka halaman login" "127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"
APP_DIR=${APP_DIR:-/var/www/csirt}
REPO_URL=${REPO_URL:-https://github.com/VascoZ/masterweb-csirt-v2.git}
REPO_BRANCH=${REPO_BRANCH:-main}
TICKET_PREFIX=${TICKET_PREFIX:-CSIRT}
BACKUP_KEEP_DAYS=${BACKUP_KEEP_DAYS:-14}
ENV_FILE="$APP_DIR/.env"
SCHEME=https; [[ "$TLS_MODE" == none ]] && SCHEME=http
APP_URL="$SCHEME://$DOMAIN"

[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "DOMAIN tidak valid: $DOMAIN"
ok "Domain $DOMAIN, TLS $TLS_MODE, direktori $APP_DIR"

# ----------------------------------------------------------------------------- 1. paket sistem
step "Memasang paket sistem"
apt-get update -q
apt-get -y -q -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold upgrade
apt-get install -y -q ca-certificates curl gnupg git unzip rsync acl cron sudo \
    software-properties-common openssl ufw fail2ban unattended-upgrades chrony

if [[ "$VERSION_ID" == 22.04 ]] && ! apt-cache policy "php${PHP_VERSION}-fpm" | grep -q 'Candidate:.*[0-9]'; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -q
fi

apt-get install -y -q nginx mariadb-server \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-opcache"
[[ "$TLS_MODE" == letsencrypt ]] && apt-get install -y -q certbot
ok "Nginx, MariaDB, PHP ${PHP_VERSION} terpasang"

# Node.js hanya untuk build aset frontend (repo resmi NodeSource, kunci terverifikasi).
if ! command -v node >/dev/null || [[ "$(node -v | sed -E 's/^v([0-9]+).*/\1/')" -lt $NODE_MAJOR ]]; then
    install -d -m 0755 /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
    apt-get update -q
    apt-get install -y -q nodejs
fi
ok "Node.js $(node -v)"

# Composer dengan verifikasi checksum installer resmi.
if ! command -v composer >/dev/null; then
    tmp=$(mktemp -d)
    expected=$(curl -fsSL https://composer.github.io/installer.sig)
    curl -fsSL https://getcomposer.org/installer -o "$tmp/composer-setup.php"
    actual=$(php -r "echo hash_file('sha384', '$tmp/composer-setup.php');")
    [[ "$expected" == "$actual" ]] || die "Checksum installer Composer tidak cocok."
    php "$tmp/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp"
fi
ok "Composer $(composer --version 2>/dev/null | awk '{print $3}')"

systemctl enable --now chrony >/dev/null 2>&1 || true
ok "Sinkronisasi waktu (chrony) aktif — penting untuk kode 2FA"

# ----------------------------------------------------------------------------- 2. database
step "Mengonfigurasi MariaDB"
systemctl enable --now mariadb
# Hanya dengarkan localhost & bersihkan bawaan yang tidak aman.
cat > /etc/mysql/mariadb.conf.d/90-csirt.cnf <<'EOF'
[mysqld]
bind-address = 127.0.0.1
skip-name-resolve
local-infile = 0
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
EOF
systemctl restart mariadb
mariadb -e "DELETE FROM mysql.global_priv WHERE User=''; DROP DATABASE IF EXISTS test; FLUSH PRIVILEGES;" 2>/dev/null || true

DB_PASSWORD=$( [[ -f "$ENV_FILE" ]] && get_env DB_PASSWORD || true )
[[ -n "$DB_PASSWORD" ]] || DB_PASSWORD=$(openssl rand -hex 24)
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
FLUSH PRIVILEGES;
SQL
# Aplikasi terhubung lewat TCP 127.0.0.1; dengan skip-name-resolve, 'localhost'
# (socket) dan '127.0.0.1' adalah host berbeda, jadi keduanya didaftarkan.
for db_host in localhost 127.0.0.1; do
    mariadb <<SQL
CREATE USER IF NOT EXISTS '$DB_USER'@'$db_host' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'$db_host' IDENTIFIED BY '$DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES ON \`$DB_NAME\`.* TO '$DB_USER'@'$db_host';
FLUSH PRIVILEGES;
SQL
done
ok "Database '$DB_NAME' dan user '$DB_USER' (hak akses terbatas) siap"

# ----------------------------------------------------------------------------- 3. kode aplikasi
step "Menyiapkan kode aplikasi"
id "$APP_USER" >/dev/null 2>&1 || useradd --system --create-home --home-dir /var/lib/csirt --shell /usr/sbin/nologin "$APP_USER"
usermod -aG "$WEB_USER" "$APP_USER"
install -d -o "$APP_USER" -g "$WEB_USER" -m 2750 "$APP_DIR"

if [[ -f "$APP_DIR/artisan" ]]; then
    ok "Kode sudah ada di $APP_DIR (dilewati; gunakan update-ubuntu.sh untuk memperbarui)"
elif [[ -n "${SOURCE_DIR:-}" ]]; then
    [[ -f "$SOURCE_DIR/artisan" ]] || die "SOURCE_DIR tidak berisi aplikasi Laravel: $SOURCE_DIR"
    rsync -a --exclude .git --exclude vendor --exclude node_modules --exclude public/build \
        --exclude public/storage --exclude .env --exclude 'storage/app/*/**' --exclude 'storage/framework/*/**' \
        --exclude 'storage/logs/*' "$SOURCE_DIR"/ "$APP_DIR"/
    chown -R "$APP_USER:$WEB_USER" "$APP_DIR"
    ok "Kode disalin dari $SOURCE_DIR"
else
    # $APP_DIR sudah ada, kosong, dan milik $APP_USER: git boleh clone langsung ke sana.
    run_as "$APP_USER" git clone --depth 1 --branch "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
    ok "Kode di-clone dari $REPO_URL ($REPO_BRANCH)"
fi

cd "$APP_DIR"
run_as "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction --no-progress
run_as "$APP_USER" bash -c 'npm ci --no-audit --no-fund --loglevel=error && npm run build && rm -rf node_modules'
ok "Dependensi PHP terpasang & aset frontend di-build"

# ----------------------------------------------------------------------------- 4. konfigurasi .env
step "Menulis konfigurasi .env"
if [[ ! -f "$ENV_FILE" ]]; then
    cp .env.example "$ENV_FILE"
    NEW_ENV=1
fi
set_env APP_NAME "$SITE_NAME"
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$APP_URL"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env SESSION_SECURE_COOKIE "$([[ $SCHEME == https ]] && echo true || echo false)"
set_env CSIRT_LOGIN_WHITELIST "$LOGIN_WHITELIST"
set_env CSIRT_TICKET_PREFIX "$TICKET_PREFIX"
[[ -n "${TEAM_EMAIL:-}" ]] && set_env CSIRT_TEAM_EMAIL "$TEAM_EMAIL"
if [[ -n "${MAIL_HOST:-}" ]]; then
    set_env MAIL_MAILER smtp
    set_env MAIL_HOST "$MAIL_HOST"
    set_env MAIL_PORT "${MAIL_PORT:-587}"
    set_env MAIL_USERNAME "${MAIL_USERNAME:-}"
    set_env MAIL_PASSWORD "${MAIL_PASSWORD:-}"
    [[ -n "${MAIL_ENCRYPTION:-}" ]] && set_env MAIL_SCHEME "$MAIL_ENCRYPTION"
    set_env MAIL_FROM_ADDRESS "${MAIL_FROM_ADDRESS:-noreply@$DOMAIN}"
elif [[ "${NEW_ENV:-0}" == 1 ]]; then
    set_env MAIL_MAILER log
    warn "SMTP belum dikonfigurasi (MAIL_MAILER=log). Isi MAIL_* di $ENV_FILE agar notifikasi aduan terkirim."
fi
# Kredensial admin tidak pernah ditulis ke .env.
for k in ADMIN_NAME ADMIN_USERNAME ADMIN_EMAIL ADMIN_PASSWORD; do set_env "$k" ""; done

chown "$APP_USER:$WEB_USER" "$ENV_FILE"; chmod 640 "$ENV_FILE"
if [[ -z "$(get_env APP_KEY)" ]]; then
    key="base64:$(openssl rand -base64 32)"
    set_env APP_KEY "$key"
    ok "APP_KEY dibuat"
fi

# ----------------------------------------------------------------------------- 5. permission
step "Mengatur kepemilikan & permission"
chown -R "$APP_USER:$WEB_USER" "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 2750 {} +
find "$APP_DIR" -type f -exec chmod 0640 {} +
chmod 0750 "$APP_DIR/artisan"
install -d "$APP_DIR"/storage/{app/public,app/private,framework/cache/data,framework/sessions,framework/views,logs} "$APP_DIR/bootstrap/cache"
chown -R "$WEB_USER:$WEB_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2770 {} +
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 0660 {} +
run_as "$APP_USER" ln -sfn ../storage/app/public "$APP_DIR/public/storage"
ok "Kode milik $APP_USER (read-only bagi PHP), storage/ & bootstrap/cache milik $WEB_USER"

# ----------------------------------------------------------------------------- 6. PHP-FPM
step "Mengonfigurasi PHP-FPM ${PHP_VERSION}"
cat > "/etc/php/${PHP_VERSION}/fpm/conf.d/99-csirt-security.ini" <<'EOF'
; Hardening PHP (Situs CSIRT)
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
allow_url_fopen = Off
allow_url_include = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,dl,show_source
file_uploads = On
upload_max_filesize = 10M
post_max_size = 30M
max_file_uploads = 10
max_execution_time = 60
max_input_time = 60
memory_limit = 256M
session.use_strict_mode = 1
session.cookie_httponly = 1
session.use_only_cookies = 1
date.timezone = Asia/Jakarta
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
EOF

cat > "/etc/php/${PHP_VERSION}/fpm/pool.d/csirt.conf" <<EOF
; Pool khusus Situs CSIRT
[csirt]
user = $WEB_USER
group = $WEB_USER
listen = $FPM_SOCKET
listen.owner = $WEB_USER
listen.group = $WEB_USER
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

security.limit_extensions = .php
; PHP hanya boleh membaca direktori aplikasi & /tmp.
php_admin_value[open_basedir] = $APP_DIR/:/tmp/:/usr/share/zoneinfo/
php_admin_value[upload_tmp_dir] = /tmp
php_admin_value[error_log] = /var/log/php${PHP_VERSION}-fpm-csirt.log
catch_workers_output = yes
EOF
touch "/var/log/php${PHP_VERSION}-fpm-csirt.log"; chown "$WEB_USER:adm" "/var/log/php${PHP_VERSION}-fpm-csirt.log"
php-fpm${PHP_VERSION} -t
systemctl enable "php${PHP_VERSION}-fpm" >/dev/null
systemctl restart "php${PHP_VERSION}-fpm"
ok "Pool 'csirt' aktif ($FPM_SOCKET), open_basedir & disable_functions diterapkan"

# ----------------------------------------------------------------------------- 7. aplikasi
step "Migrasi database & instalasi awal aplikasi"
# Hapus cache konfigurasi/route lama agar nilai .env & environment terbaru terbaca.
# (Bukan optimize:clear: itu juga mengosongkan cache database yang tabelnya belum ada.)
artisan config:clear >/dev/null
artisan route:clear >/dev/null
artisan migrate --force
artisan storage:link >/dev/null 2>&1 || true

if mariadb -N -e "SELECT COUNT(*) FROM \`$DB_NAME\`.users WHERE is_superadmin=1" 2>/dev/null | grep -qv '^0$'; then
    ok "Superadmin sudah ada (dilewati)"
    artisan csirt:install --no-interaction --skip-migrate --site-name="$SITE_NAME" >/dev/null || true
else
    ask ADMIN_EMAIL "Email superadmin"
    while true; do
        ask ADMIN_PASSWORD "Password superadmin (min. 8, huruf besar & kecil, angka, simbol)" "" secret
        if [[ ${#ADMIN_PASSWORD} -ge 8 && "$ADMIN_PASSWORD" =~ [A-Z] && "$ADMIN_PASSWORD" =~ [a-z] && "$ADMIN_PASSWORD" =~ [0-9] && "$ADMIN_PASSWORD" =~ [^A-Za-z0-9] ]]; then break; fi
        [[ "${NONINTERACTIVE:-0}" == 1 ]] && die "ADMIN_PASSWORD tidak memenuhi kebijakan password."
        echo "  Password terlalu lemah, coba lagi."; ADMIN_PASSWORD=
    done
    export ADMIN_NAME ADMIN_USERNAME ADMIN_EMAIL ADMIN_PASSWORD CSIRT_SITE_NAME="$SITE_NAME"
    # Kredensial dikirim lewat environment (tidak muncul di daftar proses / file).
    (cd "$APP_DIR" && sudo -u "$WEB_USER" --preserve-env=ADMIN_NAME,ADMIN_USERNAME,ADMIN_EMAIL,ADMIN_PASSWORD,CSIRT_SITE_NAME \
        HOME=/tmp php artisan csirt:install --no-interaction --skip-migrate)
    unset ADMIN_PASSWORD
    ok "Superadmin $ADMIN_EMAIL dibuat"
fi
artisan optimize >/dev/null
ok "Cache konfigurasi, route, dan view dibuat"

# ----------------------------------------------------------------------------- 8. Nginx & TLS
step "Mengonfigurasi Nginx & TLS"
cat > /etc/nginx/conf.d/csirt-http.conf <<'EOF'
# Pengaturan global (konteks http) untuk Situs CSIRT
server_tokens off;
limit_req_zone $binary_remote_addr zone=csirt_login:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=csirt_report:10m rate=6r/m;
limit_req_zone $binary_remote_addr zone=csirt_general:10m rate=10r/s;
limit_req_status 429;
EOF
install -d -m 0755 /var/www/letsencrypt
rm -f /etc/nginx/sites-enabled/default

write_http_only() {
    cat > /etc/nginx/sites-available/csirt <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 301 https://\$host\$request_uri; }
}
EOF
}

app_locations() {
    cat <<EOF
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 30m;
    client_body_timeout 30s;
    client_header_timeout 15s;
    keepalive_timeout 30s;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml text/plain;
    gzip_min_length 1024;

    access_log /var/log/nginx/csirt.access.log;
    error_log  /var/log/nginx/csirt.error.log warn;

    location ~ /\.(?!well-known/) { deny all; return 404; }

    location /build/ {
        add_header Cache-Control "public, max-age=31536000, immutable" always;
        add_header X-Content-Type-Options "nosniff" always;
        $HSTS_LINE
        access_log off;
        try_files \$uri =404;
    }

    location ^~ /storage/ {
        add_header X-Content-Type-Options "nosniff" always;
        add_header Cache-Control "public, max-age=86400" always;
        $HSTS_LINE
        location ~* \.(png|jpe?g|gif|webp|ico|pdf)\$ {
            add_header X-Content-Type-Options "nosniff" always;
            $HSTS_LINE
            try_files \$uri =404;
        }
        location ~* \.asc\$ {
            add_header X-Content-Type-Options "nosniff" always;
            default_type text/plain;
            try_files \$uri =404;
        }
        return 404;
    }

    location = /favicon.ico { access_log off; log_not_found off; try_files \$uri =204; }
    location = /robots.txt  { access_log off; log_not_found off; try_files \$uri =404; }
    location = /login { limit_req zone=csirt_login burst=5 nodelay; try_files \$uri /index.php?\$query_string; }
    location = /aduan { limit_req zone=csirt_report burst=3 nodelay; try_files \$uri /index.php?\$query_string; }
    location / { limit_req zone=csirt_general burst=40 nodelay; try_files \$uri \$uri/ /index.php?\$query_string; }

    location = /index.php {
        fastcgi_pass unix:$FPM_SOCKET;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ \.php\$ { return 404; }
EOF
}

write_https() {
    local cert=$1 key=$2 stapling=$3 ver listen_opts http2_line
    # Direktif "http2 on" baru ada sejak Nginx 1.25.1; Ubuntu 22.04/24.04 memakai 1.18/1.24.
    ver=$(nginx -v 2>&1 | sed -E 's/.*nginx\/([0-9.]+).*/\1/')
    if [[ "$(printf '%s\n' 1.25.1 "$ver" | sort -V | head -1)" == 1.25.1 ]]; then
        listen_opts="ssl"; http2_line="    http2 on;"
    else
        listen_opts="ssl http2"; http2_line=""
    fi
    {
        cat <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 301 https://\$host\$request_uri; }
}

server {
    listen 443 $listen_opts;
    listen [::]:443 $listen_opts;
$http2_line
    server_name $DOMAIN;

    ssl_certificate     $cert;
    ssl_certificate_key $key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:CSIRT_SSL:10m;
    ssl_session_tickets off;
EOF
        if [[ "$stapling" == on ]]; then
            echo "    ssl_stapling on;"
            echo "    ssl_stapling_verify on;"
            echo "    resolver 1.1.1.1 8.8.8.8 valid=300s;"
        fi
        echo "    $HSTS_LINE"
        echo
        app_locations
        echo "}"
    } > /etc/nginx/sites-available/csirt
}

write_http_app() {
    { echo "server {"; echo "    listen 80;"; echo "    listen [::]:80;"; echo "    server_name $DOMAIN;"; app_locations; echo "}"; } > /etc/nginx/sites-available/csirt
}

HSTS_LINE=""
case "$TLS_MODE" in
    letsencrypt)
        HSTS_LINE='add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;'
        if [[ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]]; then
            write_http_only
            ln -sfn /etc/nginx/sites-available/csirt /etc/nginx/sites-enabled/csirt
            nginx -t && systemctl reload nginx
            certbot certonly --webroot -w /var/www/letsencrypt -d "$DOMAIN" --email "$LE_EMAIL" --agree-tos --non-interactive \
                || die "Gagal mendapatkan sertifikat Let's Encrypt. Pastikan DNS $DOMAIN mengarah ke server ini dan port 80 terbuka dari internet."
        fi
        install -d /etc/letsencrypt/renewal-hooks/deploy
        printf '#!/bin/sh\nsystemctl reload nginx\n' > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
        chmod 755 /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
        write_https "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "/etc/letsencrypt/live/$DOMAIN/privkey.pem" on
        ok "Sertifikat Let's Encrypt aktif (perpanjangan otomatis lewat timer certbot)"
        ;;
    custom)
        HSTS_LINE='add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;'
        install -d -m 0750 /etc/ssl/csirt
        install -m 0644 "$CERT_FILE" /etc/ssl/csirt/fullchain.pem
        install -m 0600 "$KEY_FILE" /etc/ssl/csirt/privkey.pem
        write_https /etc/ssl/csirt/fullchain.pem /etc/ssl/csirt/privkey.pem on
        ok "Sertifikat instansi dipasang di /etc/ssl/csirt"
        ;;
    selfsigned)
        install -d -m 0750 /etc/ssl/csirt
        if [[ ! -f /etc/ssl/csirt/fullchain.pem ]]; then
            openssl req -x509 -newkey rsa:2048 -sha256 -days 825 -nodes \
                -keyout /etc/ssl/csirt/privkey.pem -out /etc/ssl/csirt/fullchain.pem \
                -subj "/CN=$DOMAIN" -addext "subjectAltName=DNS:$DOMAIN" 2>/dev/null
            chmod 600 /etc/ssl/csirt/privkey.pem
        fi
        write_https /etc/ssl/csirt/fullchain.pem /etc/ssl/csirt/privkey.pem off
        warn "Sertifikat self-signed hanya untuk uji/internal. Browser akan menampilkan peringatan."
        ;;
    none)
        write_http_app
        warn "TLS_MODE=none: situs berjalan tanpa HTTPS. Jangan gunakan untuk produksi."
        ;;
esac
ln -sfn /etc/nginx/sites-available/csirt /etc/nginx/sites-enabled/csirt
nginx -t
systemctl enable nginx >/dev/null
systemctl reload nginx || systemctl restart nginx
ok "Nginx aktif untuk $APP_URL"

# ----------------------------------------------------------------------------- 9. cron: scheduler & backup
step "Menjadwalkan scheduler & backup harian"
cat > /usr/local/sbin/csirt-backup <<EOF
#!/usr/bin/env bash
# Backup harian Situs CSIRT: database + file unggahan + bukti aduan + .env
set -euo pipefail
DEST=/var/backups/csirt
STAMP=\$(date +%F-%H%M)
umask 077
mkdir -p "\$DEST"
mariadb-dump --single-transaction --routines --triggers $DB_NAME | gzip > "\$DEST/db-\$STAMP.sql.gz"
tar -czf "\$DEST/files-\$STAMP.tar.gz" -C "$APP_DIR" storage/app .env
find "\$DEST" -type f -mtime +$BACKUP_KEEP_DAYS -delete
echo "Backup selesai: \$DEST (\$STAMP)"
EOF
chmod 750 /usr/local/sbin/csirt-backup
cat > /etc/cron.d/csirt <<EOF
# Situs CSIRT
* * * * * $WEB_USER cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1
15 2 * * * root /usr/local/sbin/csirt-backup >> /var/log/csirt-backup.log 2>&1
EOF
chmod 644 /etc/cron.d/csirt
systemctl enable --now cron >/dev/null 2>&1 || true
ok "Backup harian 02:15 ke /var/backups/csirt (disimpan $BACKUP_KEEP_DAYS hari)"

# ----------------------------------------------------------------------------- 10. hardening OS
step "Hardening sistem operasi"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
ok "Pembaruan keamanan otomatis (unattended-upgrades) aktif"

cat > /etc/fail2ban/jail.d/csirt.local <<'EOF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 6

[sshd]
enabled = true

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/csirt.error.log
maxretry = 10
EOF
touch /var/log/nginx/csirt.error.log
systemctl enable fail2ban >/dev/null 2>&1 || true
systemctl restart fail2ban || warn "fail2ban gagal dijalankan; periksa: journalctl -u fail2ban"
ok "fail2ban aktif untuk SSH & rate limit Nginx"

if [[ "${SKIP_FIREWALL:-0}" == 1 ]]; then
    warn "Konfigurasi firewall dilewati (SKIP_FIREWALL=1)."
else
    SSH_PORT=$( (sshd -T 2>/dev/null || true) | awk '/^port /{print $2; exit}'); SSH_PORT=${SSH_PORT:-22}
    ufw --force reset >/dev/null
    ufw default deny incoming >/dev/null
    ufw default allow outgoing >/dev/null
    if [[ -n "${SSH_ALLOW:-}" ]]; then
        IFS=',' read -ra ssh_sources <<< "$SSH_ALLOW"
        for src in "${ssh_sources[@]}"; do ufw allow from "$src" to any port "$SSH_PORT" proto tcp >/dev/null; done
        ok "SSH (port $SSH_PORT) hanya dari: $SSH_ALLOW"
    else
        ufw allow "$SSH_PORT"/tcp >/dev/null
        warn "SSH (port $SSH_PORT) terbuka untuk semua IP. Batasi dengan SSH_ALLOW=<ip/cidr> lalu jalankan ulang."
    fi
    ufw allow 80/tcp >/dev/null
    [[ "$SCHEME" == https ]] && ufw allow 443/tcp >/dev/null
    ufw --force enable >/dev/null
    ok "UFW aktif: hanya SSH, HTTP$([[ $SCHEME == https ]] && echo ', HTTPS') yang terbuka"
fi

# ----------------------------------------------------------------------------- 11. verifikasi
step "Verifikasi"
code=$(curl -s -o /dev/null -w '%{http_code}' -k --resolve "$DOMAIN:80:127.0.0.1" --resolve "$DOMAIN:443:127.0.0.1" "$APP_URL/up" || true)
[[ "$code" == 200 ]] && ok "Health check $APP_URL/up → 200" || warn "Health check $APP_URL/up mengembalikan '$code'. Periksa $APP_DIR/storage/logs dan /var/log/nginx/csirt.error.log"
code=$(curl -s -o /dev/null -w '%{http_code}' -k --resolve "$DOMAIN:80:127.0.0.1" --resolve "$DOMAIN:443:127.0.0.1" "$APP_URL/.env" || true)
[[ "$code" == 404 ]] && ok "File .env tidak dapat diakses dari web" || warn "Akses $APP_URL/.env mengembalikan '$code' (seharusnya 404)!"

umask 077
cat > "$INFO_FILE" <<EOF
Situs CSIRT — informasi instalasi ($(date -Is))
==============================================
URL situs        : $APP_URL
Halaman login    : $APP_URL/csirt-login  (hanya dari: $LOGIN_WHITELIST)
Direktori        : $APP_DIR
Konfigurasi      : $ENV_FILE
Database         : $DB_NAME  (user: $DB_USER, password: $DB_PASSWORD)
Backup           : /var/backups/csirt  (harian 02:15, simpan $BACKUP_KEEP_DAYS hari)
Log instalasi    : $LOG_FILE
Update aplikasi  : sudo bash $APP_DIR/scripts/update-ubuntu.sh
EOF

echo
echo "${c_green}=====================================================================${c_reset}"
echo "${c_green} Instalasi selesai.${c_reset}"
echo "   Situs        : $APP_URL"
echo "   Login        : $APP_URL/csirt-login"
echo "   Info & kredensial DB tersimpan di $INFO_FILE (hanya root)"
echo
echo " Langkah berikutnya:"
echo "   1. Login sebagai superadmin dari IP yang ada di whitelist, lalu aktifkan 2FA."
echo "   2. Lengkapi Kontak & Peta, Logo & Banner, Profil, RFC 2350, dan Kunci PGP."
echo "   3. Periksa $APP_URL/.well-known/security.txt"
if ((${#WARNINGS[@]})); then
    echo
    echo "${c_yellow} Perhatian:${c_reset}"
    for w in "${WARNINGS[@]}"; do echo "   - $w"; done
fi
echo "${c_green}=====================================================================${c_reset}"
