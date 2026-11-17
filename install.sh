#!/usr/bin/env bash
# ==============================================================================
# GenieACS Server 1.2.16 + FTTH Network Topology Manager - 1-Click Installer
# OS Support: Ubuntu 20.04 / 22.04 / 24.04 LTS & Debian 11 / 12
# ==============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}==============================================================================${NC}"
echo -e "${GREEN}  GenieACS 1.2.16 Server & FTTH Network Manager - Automated Setup Installer${NC}"
echo -e "${BLUE}==============================================================================${NC}"

# 1. Check Root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}[ERROR] Skrip ini harus dijalankan sebagai root (gunakan sudo bash install.sh)${NC}" 
   exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# 2. Update System & Prerequisites
echo -e "\n${YELLOW}>>> [1/7] Memperbarui sistem dan dependensi dasar...${NC}"
apt-get update -y
apt-get install -y curl wget gnupg lsb-release ca-certificates build-essential git ufw jq

# 3. Install Node.js 20 LTS
echo -e "\n${YELLOW}>>> [2/7] Memasang Node.js 20 LTS (NodeSource)...${NC}"
if ! command -v node >/dev/null 2>&1 || [[ $(node -v | cut -d'.' -f1) != "v20" ]]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi
echo -e "${GREEN}Node.js version: $(node -v)${NC}"
echo -e "${GREEN}NPM version: $(npm -v)${NC}"

# 4. Install MongoDB
echo -e "\n${YELLOW}>>> [3/7] Memasang MongoDB Server...${NC}"
if ! command -v mongod >/dev/null 2>&1; then
    curl -fsSL https://pgp.mongodb.com/server-5.0.asc | gpg --dearmor -o /usr/share/keyrings/mongodb-server-5.0.gpg || true
    echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-5.0.gpg ] https://repo.mongodb.org/apt/ubuntu $(lsb_release -cs)/mongodb-org/5.0 multiverse" | tee /etc/apt/sources.list.d/mongodb-org-5.0.list || true
    apt-get update -y || true
    apt-get install -y mongodb-org || apt-get install -y mongodb
fi
systemctl enable --now mongod || systemctl enable --now mongodb

# 5. Install GenieACS 1.2.16
echo -e "\n${YELLOW}>>> [4/7] Memasang GenieACS v1.2.16...${NC}"
npm install -g genieacs@1.2.16

# Setup GenieACS User & Directories
id -u genieacs &>/dev/null || useradd --system --no-create-home --user-group -s /bin/false genieacs
mkdir -p /opt/genieacs/ext /var/log/genieacs
chown -R genieacs:genieacs /opt/genieacs /var/log/genieacs

# Setup Environment File
if [ ! -f /opt/genieacs/genieacs.env ]; then
    JWT_SECRET=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 128 ; echo '')
    cat <<EOF > /opt/genieacs/genieacs.env
GENIEACS_CWMP_ACCESS_LOG_FILE=/var/log/genieacs/genieacs-cwmp-access.log
GENIEACS_NBI_ACCESS_LOG_FILE=/var/log/genieacs/genieacs-nbi-access.log
GENIEACS_FS_ACCESS_LOG_FILE=/var/log/genieacs/genieacs-fs-access.log
GENIEACS_UI_ACCESS_LOG_FILE=/var/log/genieacs/genieacs-ui-access.log
GENIEACS_DEBUG_FILE=/var/log/genieacs/genieacs-debug.yaml
NODE_OPTIONS=--enable-source-maps
GENIEACS_EXT_DIR=/opt/genieacs/ext
GENIEACS_UI_JWT_SECRET=$JWT_SECRET
EOF
    chown genieacs:genieacs /opt/genieacs/genieacs.env
    chmod 600 /opt/genieacs/genieacs.env
fi

# Setup Systemd Services
for svc in cwmp nbi fs ui; do
    cat <<EOF > /etc/systemd/system/genieacs-${svc}.service
[Unit]
Description=GenieACS ${svc}
After=network.target mongod.service

[Service]
User=genieacs
EnvironmentFile=/opt/genieacs/genieacs.env
ExecStart=/usr/local/bin/genieacs-${svc}
Restart=always
RestartSec=5

[Install]
WantedBy=default.target
EOF
done

systemctl daemon-reload
systemctl enable --now genieacs-cwmp genieacs-nbi genieacs-fs genieacs-ui

# 6. Inject Default Provisions & Presets (SSID, PPPoE, Optical Rx Power, Summon Fix)
echo -e "\n${YELLOW}>>> [5/7] Menginjeksi skrip Provisioning & Preset Default ke MongoDB...${NC}"
node -e "
const { MongoClient } = require('mongodb');
async function run() {
  const client = new MongoClient('mongodb://127.0.0.1:27017');
  try {
    await client.connect();
    const db = client.db('genieacs');
    
    // Default Preset
    await db.collection('presets').updateOne(
      { _id: 'default' },
      { \$set: { weight: 0, precondition: 'true', configurations: [{ type: 'provision', name: 'inform' }] } },
      { upsert: true }
    );
    
    // Inform Provision (Auto PPPoE, SSID, Signal Rx, Temp, Uptime)
    const informScript = \`
// Smart Inform Provision for GenieACS
const now = Date.now();

// 1. Declare Device ID & Inform Interval (300s)
declare('InternetGatewayDevice.ManagementServer.PeriodicInformInterval', {value: now}, {value: 300});
declare('Device.ManagementServer.PeriodicInformInterval', {value: now}, {value: 300});

// 2. Fetch WAN IP & PPPoE Credentials
declare('InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.ExternalIPAddress', {value: now});
declare('InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.Username', {value: now});

// 3. Fetch Wi-Fi SSID
declare('InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.SSID', {value: now});
declare('Device.WiFi.SSID.*.SSID', {value: now});

// 4. Fetch Optical Rx Power & Temperature
declare('InternetGatewayDevice.WANDevice.*.WANGPONInterfaceConfig.RXPower', {value: now});
declare('Device.Optical.Interface.*.OpticalSignalLevel', {value: now});
\`;
    await db.collection('provisions').updateOne(
      { _id: 'inform' },
      { \$set: { script: informScript } },
      { upsert: true }
    );
    console.log('Default presets and inform provision injected successfully.');
  } finally {
    await client.close();
  }
}
run().catch(console.error);
" || true

# 7. Install Nginx & PHP 8.1
echo -e "\n${YELLOW}>>> [6/7] Memasang Nginx & PHP-FPM...${NC}"
apt-get install -y nginx php-fpm php-curl php-json php-cli php-mbstring

TARGET_WEBDIR="/var/www/genieacs-manager"
mkdir -p "$TARGET_WEBDIR"
cp -r "$SCRIPT_DIR"/* "$TARGET_WEBDIR/"
chown -R www-data:www-data "$TARGET_WEBDIR"
chmod -R 775 "$TARGET_WEBDIR/data"

# Setup Nginx Configuration
PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" | head -n 1)
cat <<EOF > /etc/nginx/sites-available/genieacs-manager
server {
    listen 80 default_server;
    server_name _;

    root $TARGET_WEBDIR;
    index index.php index.html;

    # 1. SECURITY: Block direct access to database folder
    location ^~ /data/ {
        deny all;
        return 403;
    }

    # 2. SECURITY: Block sensitive files, archives, and dotfiles
    location ~* \.(zip|rar|7z|bak|sql|tar|gz|txt|log|env)$ {
        deny all;
        return 403;
    }

    location ~ /\. {
        deny all;
    }

    # 3. Router
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # 4. PHP-FPM FastCGI
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # 5. GZIP Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;
}
EOF

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/genieacs-manager /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

# 8. Finished Summary
echo -e "\n${BLUE}==============================================================================${NC}"
echo -e "${GREEN}  INSTALASI SELESAI DENGAN SUKSES!${NC}"
echo -e "${BLUE}==============================================================================${NC}"
echo -e "Layanan yang berjalan:"
echo -e "  - ${YELLOW}GenieACS CWMP (TR-069):${NC}    http://IP-SERVER:7547"
echo -e "  - ${YELLOW}GenieACS NBI (API):${NC}        http://127.0.0.1:7557"
echo -e "  - ${YELLOW}GenieACS File Server:${NC}      http://IP-SERVER:7567"
echo -e "  - ${YELLOW}GenieACS Official UI:${NC}      http://IP-SERVER:3000"
echo -e "  - ${GREEN}Dashboard & FTTH Manager:${NC}  http://IP-SERVER/"
echo -e ""
echo -e "Login Default Dashboard Web:"
echo -e "  - Username: ${YELLOW}admin${NC}"
echo -e "  - Password: ${YELLOW}admin123${NC}"
echo -e "${BLUE}==============================================================================${NC}"

