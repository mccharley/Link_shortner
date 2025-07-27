#!/bin/bash

# LinkShortener Automated Deployment Script
# This script completely automates the deployment of the LinkShortener API
# No manual intervention required - just run and go!

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="linkshortener"
DOMAIN="localhost"
EMAIL="admin@${DOMAIN}"
DB_PASSWORD=$(openssl rand -base64 32)
REDIS_PASSWORD=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 64)
SESSION_SECRET=$(openssl rand -base64 64)
ENCRYPTION_KEY=$(openssl rand -base64 32)

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}
╔══════════════════════════════════════════════════════════════╗
║                 LinkShortener Auto Deployment               ║
║              Complete Automated Setup Script                ║
╚══════════════════════════════════════════════════════════════╝
${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to detect OS
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command_exists apt-get; then
            OS="ubuntu"
        elif command_exists yum; then
            OS="centos"
        elif command_exists dnf; then
            OS="fedora"
        else
            OS="linux"
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        OS="macos"
    else
        OS="unknown"
    fi
    print_status "Detected OS: $OS"
}

# Function to install Docker
install_docker() {
    print_status "Installing Docker..."
    
    case $OS in
        "ubuntu")
            # Update package index
            sudo apt-get update
            
            # Install required packages
            sudo apt-get install -y \
                apt-transport-https \
                ca-certificates \
                curl \
                gnupg \
                lsb-release \
                openssl
            
            # Add Docker GPG key
            curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
            
            # Add Docker repository
            echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
            
            # Install Docker
            sudo apt-get update
            sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
            ;;
        "centos"|"fedora")
            # Install required packages
            sudo yum install -y yum-utils device-mapper-persistent-data lvm2 openssl
            
            # Add Docker repository
            sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
            
            # Install Docker
            sudo yum install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
            
            # Start Docker service
            sudo systemctl start docker
            sudo systemctl enable docker
            ;;
        "macos")
            if command_exists brew; then
                brew install --cask docker
                print_warning "Please start Docker Desktop manually and then re-run this script"
                exit 1
            else
                print_error "Please install Docker Desktop for Mac manually from https://docker.com/products/docker-desktop"
                exit 1
            fi
            ;;
        *)
            print_error "Unsupported OS. Please install Docker manually."
            exit 1
            ;;
    esac
    
    # Add current user to docker group (Linux only)
    if [[ "$OS" != "macos" ]]; then
        sudo usermod -aG docker $USER
        print_warning "You may need to log out and back in for Docker permissions to take effect"
    fi
    
    print_status "Docker installation completed"
}

# Function to install Docker Compose (if not included)
install_docker_compose() {
    if ! command_exists docker-compose; then
        print_status "Installing Docker Compose..."
        
        # Get latest version
        DOCKER_COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep 'tag_name' | cut -d\" -f4)
        
        # Download and install
        sudo curl -L "https://github.com/docker/compose/releases/download/${DOCKER_COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        sudo chmod +x /usr/local/bin/docker-compose
        
        print_status "Docker Compose installation completed"
    fi
}

# Function to create directory structure
create_directories() {
    print_status "Creating directory structure..."
    
    mkdir -p docker/{apache,php,mysql,redis,nginx,supervisor,cron,ssl,prometheus,grafana}
    mkdir -p docker/mysql/init-scripts
    mkdir -p docker/grafana/provisioning/{dashboards,datasources}
    mkdir -p storage/{uploads,cache,sessions}
    mkdir -p logs
    mkdir -p backups
    mkdir -p scripts
    
    print_status "Directory structure created"
}

# Function to generate SSL certificates
generate_ssl_certificates() {
    print_status "Generating SSL certificates..."
    
    # Create SSL directory
    mkdir -p docker/ssl
    
    # Generate private key
    openssl genrsa -out docker/ssl/private.key 2048
    
    # Generate certificate signing request
    openssl req -new -key docker/ssl/private.key -out docker/ssl/certificate.csr -subj "/C=US/ST=State/L=City/O=Organization/CN=${DOMAIN}"
    
    # Generate self-signed certificate
    openssl x509 -req -days 365 -in docker/ssl/certificate.csr -signkey docker/ssl/private.key -out docker/ssl/certificate.crt
    
    # Set proper permissions
    chmod 600 docker/ssl/private.key
    chmod 644 docker/ssl/certificate.crt
    
    print_status "SSL certificates generated"
}

# Function to create configuration files
create_config_files() {
    print_status "Creating configuration files..."
    
    # Apache configuration
    cat > docker/apache/000-default.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/public
    
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
        
        # Enable URL rewriting
        RewriteEngine On
        
        # Handle Angular Router
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.php [L]
    </Directory>
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com;"
    
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName localhost
    DocumentRoot /var/www/html/public
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/linkshortener/certificate.crt
    SSLCertificateKeyFile /etc/ssl/certs/linkshortener/private.key
    
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
        
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.php [L]
    </Directory>
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    
    ErrorLog ${APACHE_LOG_DIR}/ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/ssl_access.log combined
</VirtualHost>
EOF

    # Apache main configuration
    cat > docker/apache/apache2.conf << 'EOF'
DefaultRuntimeDir ${APACHE_RUN_DIR}
PidFile ${APACHE_PID_FILE}
Timeout 300
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 5

User ${APACHE_RUN_USER}
Group ${APACHE_RUN_GROUP}

HostnameLookups Off

ErrorLog ${APACHE_LOG_DIR}/error.log
LogLevel warn

IncludeOptional mods-enabled/*.load
IncludeOptional mods-enabled/*.conf

Include ports.conf

<Directory />
    Options FollowSymLinks
    AllowOverride None
    Require all denied
</Directory>

<Directory /usr/share>
    AllowOverride None
    Require all granted
</Directory>

<Directory /var/www/html>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

AccessFileName .htaccess

<FilesMatch "^\.ht">
    Require all denied
</FilesMatch>

LogFormat "%v:%p %h %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" vhost_combined
LogFormat "%h %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" combined
LogFormat "%h %l %u %t \"%r\" %>s %O" common
LogFormat "%{Referer}i -> %U" referer
LogFormat "%{User-agent}i" agent

IncludeOptional conf-enabled/*.conf
IncludeOptional sites-enabled/*.conf
EOF

    # PHP configuration
    cat > docker/php/php.ini << 'EOF'
[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
unserialize_callback_func =
serialize_precision = -1
disable_functions =
disable_classes =
zend.enable_gc = On
zend.exception_ignore_args = On

expose_php = Off
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On
log_errors_max_len = 1024
ignore_repeated_errors = Off
ignore_repeated_source = Off
report_memleaks = On
variables_order = "GPCS"
request_order = "GP"
register_argc_argv = Off
auto_globals_jit = On
post_max_size = 100M
auto_prepend_file =
auto_append_file =
default_mimetype = "text/html"
default_charset = "UTF-8"

include_path = ".:/usr/local/lib/php"
doc_root =
user_dir =
enable_dl = Off
file_uploads = On
upload_max_filesize = 100M
max_file_uploads = 20
allow_url_fopen = On
allow_url_include = Off
default_socket_timeout = 60

[CLI Server]
cli_server.color = On

[Date]
date.timezone = UTC

[filter]
filter.default = unsafe_raw
filter.default_flags =

[iconv]
iconv.input_encoding = UTF-8
iconv.internal_encoding = UTF-8
iconv.output_encoding = UTF-8

[intl]
intl.default_locale = en_US.UTF-8

[sqlite3]
sqlite3.extension_dir =

[Pcre]
pcre.backtrack_limit = 100000
pcre.recursion_limit = 100000

[Pdo]
pdo_mysql.default_socket =

[Pdo_mysql]
pdo_mysql.default_socket =

[Phar]
phar.readonly = Off
phar.require_hash = On

[mail function]
SMTP = localhost
smtp_port = 25
mail.add_x_header = Off

[ODBC]
odbc.allow_persistent = On
odbc.check_persistent = On
odbc.max_persistent = -1
odbc.max_links = -1
odbc.defaultlrl = 4096
odbc.defaultbinmode = 1

[MySQLi]
mysqli.max_persistent = -1
mysqli.allow_persistent = On
mysqli.max_links = -1
mysqli.default_port = 3306
mysqli.default_socket =
mysqli.default_host =
mysqli.default_user =
mysqli.default_pw =
mysqli.reconnect = Off

[mysqlnd]
mysqlnd.collect_statistics = On
mysqlnd.collect_memory_statistics = Off

[OPcache]
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1

[curl]
curl.cainfo =

[openssl]
openssl.cafile =
openssl.capath =

[ffi]
ffi.enable = "preload"
EOF

    # OPcache configuration
    cat > docker/php/opcache.ini << 'EOF'
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.max_wasted_percentage=5
opcache.use_cwd=1
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.revalidate_path=0
opcache.save_comments=1
opcache.fast_shutdown=1
opcache.enable_file_override=0
opcache.optimization_level=0xffffffff
opcache.inherited_hack=1
opcache.dups_fix=0
opcache.blacklist_filename=
EOF

    # MySQL configuration
    cat > docker/mysql/my.cnf << 'EOF'
[mysqld]
# Basic Settings
user = mysql
pid-file = /var/run/mysqld/mysqld.pid
socket = /var/run/mysqld/mysqld.sock
port = 3306
basedir = /usr
datadir = /var/lib/mysql
tmpdir = /tmp
lc-messages-dir = /usr/share/mysql

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
init-connect = 'SET NAMES utf8mb4'

# Performance Settings
max_connections = 200
max_allowed_packet = 64M
thread_stack = 256K
thread_cache_size = 8
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# InnoDB Settings
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_file_per_table = 1
innodb_flush_method = O_DIRECT

# Logging
log_error = /var/log/mysql/error.log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Binary Logging
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 10
max_binlog_size = 100M

[mysql]
default-character-set = utf8mb4

[client]
default-character-set = utf8mb4
EOF

    # Redis configuration
    cat > docker/redis/redis.conf << 'EOF'
# Redis Configuration
bind 0.0.0.0
port 6379
timeout 0
keepalive 300

# Persistence
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /data

# Append Only File
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# Memory Management
maxmemory-policy allkeys-lru
maxclients 10000

# Logging
loglevel notice
logfile ""

# Security
requirepass redis_password_2024
EOF

    # Supervisor configuration
    cat > docker/supervisor/supervisord.conf << 'EOF'
[supervisord]
nodaemon=true
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
childlogdir=/var/log/supervisor/

[program:apache2]
command=/usr/sbin/apache2ctl -D FOREGROUND
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/apache2.log
stderr_logfile=/var/log/supervisor/apache2_error.log

[program:cron]
command=/usr/sbin/cron -f
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/cron.log
stderr_logfile=/var/log/supervisor/cron_error.log

[program:queue-worker]
command=php /var/www/html/scripts/queue-worker.php
directory=/var/www/html
autostart=true
autorestart=true
numprocs=2
stdout_logfile=/var/log/supervisor/queue-worker.log
stderr_logfile=/var/log/supervisor/queue-worker_error.log
EOF

    # Cron jobs
    cat > docker/cron/linkshortener-cron << 'EOF'
# LinkShortener Cron Jobs

# Cleanup expired links every hour
0 * * * * www-data cd /var/www/html && php scripts/cleanup.php >> /var/log/cron.log 2>&1

# Generate analytics reports daily at 2 AM
0 2 * * * www-data cd /var/www/html && php scripts/generate-reports.php >> /var/log/cron.log 2>&1

# Process revenue calculations daily at 3 AM
0 3 * * * www-data cd /var/www/html && php scripts/calculate-revenue.php >> /var/log/cron.log 2>&1

# Backup database daily at 4 AM
0 4 * * * www-data cd /var/www/html && php scripts/backup.php >> /var/log/cron.log 2>&1

# Clear old logs weekly on Sunday at 5 AM
0 5 * * 0 www-data cd /var/www/html && php scripts/clear-logs.php >> /var/log/cron.log 2>&1

# Update partner statistics every 15 minutes
*/15 * * * * www-data cd /var/www/html && php scripts/update-stats.php >> /var/log/cron.log 2>&1
EOF

    # Nginx configuration (for load balancing)
    cat > docker/nginx/nginx.conf << 'EOF'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 100M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=admin:10m rate=5r/s;

    include /etc/nginx/conf.d/*.conf;
}
EOF

    cat > docker/nginx/default.conf << 'EOF'
upstream linkshortener_app {
    server app:80;
}

server {
    listen 80;
    server_name localhost;

    location / {
        proxy_pass http://linkshortener_app;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Rate limiting
        limit_req zone=api burst=20 nodelay;
    }

    location /admin {
        proxy_pass http://linkshortener_app;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Admin rate limiting
        limit_req zone=admin burst=10 nodelay;
    }
}
EOF

    # Prometheus configuration
    cat > docker/prometheus/prometheus.yml << 'EOF'
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  # - "first_rules.yml"
  # - "second_rules.yml"

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  - job_name: 'linkshortener'
    static_configs:
      - targets: ['app:80']
    metrics_path: '/metrics'
    scrape_interval: 30s
EOF

    print_status "Configuration files created"
}

# Function to create necessary scripts
create_scripts() {
    print_status "Creating utility scripts..."
    
    # Health check script
    cat > scripts/health-check.php << 'EOF'
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LinkShortener\Config\Database;
use LinkShortener\Config\Cache;

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'services' => []
];

try {
    // Check database
    $db = Database::getConnection();
    $stmt = $db->query('SELECT 1');
    $health['services']['database'] = 'healthy';
} catch (Exception $e) {
    $health['services']['database'] = 'unhealthy';
    $health['status'] = 'unhealthy';
}

try {
    // Check Redis
    $cache = new Cache();
    $cache->set('health_check', 'ok', 10);
    $value = $cache->get('health_check');
    $health['services']['redis'] = $value === 'ok' ? 'healthy' : 'unhealthy';
} catch (Exception $e) {
    $health['services']['redis'] = 'unhealthy';
    $health['status'] = 'unhealthy';
}

http_response_code($health['status'] === 'healthy' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);
EOF

    # Queue worker script
    cat > scripts/queue-worker.php << 'EOF'
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LinkShortener\Services\QueueService;

$queueService = new QueueService();

echo "Queue worker started at " . date('Y-m-d H:i:s') . "\n";

while (true) {
    try {
        $queueService->processJobs();
        sleep(5); // Wait 5 seconds between job processing
    } catch (Exception $e) {
        error_log("Queue worker error: " . $e->getMessage());
        sleep(10); // Wait longer on error
    }
}
EOF

    # Cleanup script
    cat > scripts/cleanup.php << 'EOF'
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LinkShortener\Services\MaintenanceService;

$maintenance = new MaintenanceService();

echo "Starting cleanup at " . date('Y-m-d H:i:s') . "\n";

// Clean expired links
$expiredLinks = $maintenance->cleanupExpiredLinks();
echo "Cleaned up {$expiredLinks} expired links\n";

// Clean old analytics data
$oldAnalytics = $maintenance->cleanupOldAnalytics();
echo "Cleaned up {$oldAnalytics} old analytics records\n";

// Optimize database
$maintenance->optimizeDatabase();
echo "Database optimization completed\n";

echo "Cleanup completed at " . date('Y-m-d H:i:s') . "\n";
EOF

    # Make scripts executable
    chmod +x scripts/*.php
    chmod +x scripts/*.sh
    
    print_status "Utility scripts created"
}

# Function to create package.json for frontend assets
create_package_json() {
    print_status "Creating package.json for frontend assets..."
    
    cat > package.json << 'EOF'
{
  "name": "linkshortener-admin",
  "version": "1.0.0",
  "description": "LinkShortener Admin Panel Assets",
  "main": "index.js",
  "scripts": {
    "build": "echo 'No build process needed - using CDN assets'",
    "dev": "echo 'Development mode'",
    "watch": "echo 'Watch mode'"
  },
  "dependencies": {},
  "devDependencies": {},
  "author": "LinkShortener Team",
  "license": "MIT"
}
EOF

    print_status "Package.json created"
}

# Function to create environment file
create_env_file() {
    print_status "Creating environment configuration..."
    
    cat > .env << EOF
# Enhanced Link Shortener API Configuration

# Application Settings
APP_NAME="LinkShortener API"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_TIMEZONE=UTC

# Database Configuration
DB_HOST=mysql
DB_PORT=3306
DB_NAME=linkshortener_api
DB_USER=linkshortener
DB_PASS=${DB_PASSWORD}

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_DATABASE=0

# Security Settings
JWT_SECRET=${JWT_SECRET}
SESSION_SECRET=${SESSION_SECRET}
ENCRYPTION_KEY=${ENCRYPTION_KEY}
BCRYPT_ROUNDS=12

# API Settings
API_VERSION=v1
API_RATE_LIMIT=1000
API_RATE_LIMIT_WINDOW=3600

# Stripe Payment Configuration (Update with your keys)
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key
STRIPE_SECRET_KEY=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# Email Configuration (Update with your SMTP settings)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@linkshortener.com
MAIL_FROM_NAME="LinkShortener API"

# Two-Factor Authentication
MFA_ISSUER="LinkShortener API"
MFA_WINDOW=1

# Advertisement Settings
AD_DEFAULT_DURATION=15
AD_MIN_DURATION=5
AD_MAX_DURATION=30
AD_DEFAULT_CPM=2.5000

# Revenue Sharing
DEFAULT_REVENUE_SHARE=0.50
MIN_PAYOUT_AMOUNT=50.00

# File Storage
STORAGE_DRIVER=local
STORAGE_PATH=storage/uploads

# Analytics & Tracking
GOOGLE_ANALYTICS_ID=

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_MAX_FILES=14

# Cache Settings
CACHE_DRIVER=redis
CACHE_PREFIX=linkshortener_

# Queue Settings
QUEUE_CONNECTION=redis
QUEUE_DEFAULT=default

# Partner Settings
PARTNER_ID_LENGTH=12
SHORT_CODE_LENGTH=6
SHORT_CODE_ALPHABET=abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789

# Security Headers
CORS_ALLOWED_ORIGINS=*
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS_PER_MINUTE=60
RATE_LIMIT_REQUESTS_PER_HOUR=1000

# Monitoring & Health Checks
HEALTH_CHECK_ENABLED=true
METRICS_ENABLED=true
EOF

    print_status "Environment configuration created"
}

# Function to create database initialization script
create_db_init_script() {
    print_status "Creating database initialization script..."
    
    cat > docker/mysql/init-scripts/02-admin-user.sql << 'EOF'
-- Create default admin user
INSERT INTO admin_users (
    username, 
    email, 
    password_hash, 
    role, 
    active, 
    created_at, 
    updated_at
) VALUES (
    'admin', 
    'admin@linkshortener.com', 
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'super_admin', 
    1, 
    NOW(), 
    NOW()
) ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Create default subscription plans (if not exists)
INSERT IGNORE INTO subscription_plans (id, name, description, price_monthly, price_yearly, api_requests_limit, features, active, created_at, updated_at) VALUES
('free', 'Free Plan', 'Basic features for getting started', 0.00, 0.00, 1000, '["basic_analytics", "standard_support"]', 1, NOW(), NOW()),
('starter', 'Starter Plan', 'Perfect for small businesses', 29.99, 299.99, 10000, '["advanced_analytics", "custom_domains", "priority_support", "bulk_operations"]', 1, NOW(), NOW()),
('professional', 'Professional Plan', 'For growing businesses', 79.99, 799.99, 50000, '["advanced_analytics", "custom_domains", "priority_support", "bulk_operations", "white_label", "api_access"]', 1, NOW(), NOW()),
('enterprise', 'Enterprise Plan', 'For large organizations', 199.99, 1999.99, 200000, '["advanced_analytics", "custom_domains", "priority_support", "bulk_operations", "white_label", "api_access", "dedicated_support", "sla_guarantee"]', 1, NOW(), NOW());

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, updated_at) VALUES
('short_code_length', '6', NOW()),
('short_code_alphabet', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', NOW()),
('default_expiration_days', '0', NOW()),
('enable_analytics', 'true', NOW()),
('enable_custom_codes', 'true', NOW()),
('maintenance_mode', 'false', NOW()),
('default_ad_duration', '15', NOW()),
('max_ad_duration', '30', NOW()),
('min_ad_duration', '5', NOW()),
('default_ad_cpm', '2.5000', NOW()),
('default_revenue_share', '0.50', NOW()),
('min_payout_amount', '50.00', NOW()),
('api_rate_limit_default', '1000', NOW()),
('session_timeout_hours', '24', NOW()),
('max_login_attempts', '5', NOW()),
('lockout_duration_minutes', '30', NOW());

-- Create admin audit log table if it doesn't exist
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    data JSON,
    admin_user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_user_id (admin_user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admin users table if it doesn't exist
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'system_admin', 'partner_manager', 'content_moderator', 'analytics_manager', 'support_agent') DEFAULT 'support_agent',
    active BOOLEAN DEFAULT 1,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    mfa_enabled BOOLEAN DEFAULT 0,
    mfa_secret VARCHAR(32),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create partner blacklist table if it doesn't exist
CREATE TABLE IF NOT EXISTS partner_blacklist (
    partner_id VARCHAR(12) PRIMARY KEY,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create domain blacklist table if it doesn't exist
CREATE TABLE IF NOT EXISTS domain_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) UNIQUE NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF

    print_status "Database initialization script created"
}

# Function to start the application
start_application() {
    print_status "Starting LinkShortener application..."
    
    # Build and start containers
    docker-compose build --no-cache
    docker-compose up -d
    
    # Wait for services to be ready
    print_status "Waiting for services to start..."
    sleep 30
    
    # Check if services are running
    if docker-compose ps | grep -q "Up"; then
        print_status "Services started successfully!"
    else
        print_error "Some services failed to start. Check logs with: docker-compose logs"
        exit 1
    fi
}

# Function to display final information
display_final_info() {
    print_header
    
    cat << EOF
${GREEN}🎉 LinkShortener API has been successfully deployed!${NC}

${BLUE}📋 Access Information:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

${YELLOW}🌐 Main Application:${NC}
   • HTTP:  http://localhost
   • HTTPS: https://localhost (self-signed certificate)

${YELLOW}👨‍💼 Admin Panel:${NC}
   • URL:      http://localhost/admin
   • Username: admin
   • Password: password
   • Email:    admin@linkshortener.com

${YELLOW}🗄️  Database Access:${NC}
   • phpMyAdmin: http://localhost:8081
   • Host:       localhost:3306
   • Database:   linkshortener_api
   • Username:   linkshortener
   • Password:   ${DB_PASSWORD}

${YELLOW}🔄 Redis Cache:${NC}
   • RedisInsight: http://localhost:8082
   • Host:         localhost:6379
   • Password:     ${REDIS_PASSWORD}

${BLUE}🛠️  Management Commands:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

${GREEN}# View running services${NC}
docker-compose ps

${GREEN}# View logs${NC}
docker-compose logs -f

${GREEN}# Stop all services${NC}
docker-compose down

${GREEN}# Restart services${NC}
docker-compose restart

${GREEN}# Update application${NC}
docker-compose build --no-cache && docker-compose up -d

${GREEN}# Backup database${NC}
docker-compose exec mysql mysqldump -u linkshortener -p${DB_PASSWORD} linkshortener_api > backup.sql

${GREEN}# Access application container${NC}
docker-compose exec app bash

${BLUE}📁 Important Files:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• Configuration: .env
• Database Schema: database/enhanced_schema.sql
• Docker Compose: docker-compose.yml
• SSL Certificates: docker/ssl/
• Application Logs: logs/
• Storage: storage/

${BLUE}🔐 Security Notes:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• Change default admin password immediately
• Update Stripe API keys in .env file
• Configure SMTP settings for email functionality
• Replace self-signed SSL certificates for production
• Review and update security settings in admin panel

${BLUE}📚 Documentation:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• API Documentation: API_DOCUMENTATION.md
• Service Flows: SERVICE_FLOWS_DOCUMENTATION.md
• Architecture: ENHANCED_ARCHITECTURE_DOCUMENTATION.md
• Admin Panel: ADMIN_PANEL_DOCUMENTATION.md

${GREEN}✅ Your LinkShortener API is now ready to use!${NC}

EOF
}

# Main execution
main() {
    print_header
    
    print_status "Starting automated LinkShortener deployment..."
    
    # Detect operating system
    detect_os
    
    # Check if Docker is installed
    if ! command_exists docker; then
        print_status "Docker not found. Installing Docker..."
        install_docker
    else
        print_status "Docker is already installed"
    fi
    
    # Check if Docker Compose is installed
    if ! command_exists docker-compose && ! docker compose version >/dev/null 2>&1; then
        install_docker_compose
    else
        print_status "Docker Compose is already installed"
    fi
    
    # Create directory structure
    create_directories
    
    # Generate SSL certificates
    generate_ssl_certificates
    
    # Create configuration files
    create_config_files
    
    # Create utility scripts
    create_scripts
    
    # Create package.json
    create_package_json
    
    # Create environment file
    create_env_file
    
    # Create database initialization script
    create_db_init_script
    
    # Start the application
    start_application
    
    # Display final information
    display_final_info
}

# Run main function
main "$@"