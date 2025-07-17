#!/bin/bash

# Modern Link Shortener Installation Script
# This script sets up the application with all dependencies

set -e

echo "🔗 Modern Link Shortener Installation Script"
echo "=============================================="

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   echo "❌ This script should not be run as root"
   exit 1
fi

# Check for required commands
command -v php >/dev/null 2>&1 || { echo "❌ PHP is required but not installed. Aborting." >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "❌ Composer is required but not installed. Aborting." >&2; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "❌ MySQL is required but not installed. Aborting." >&2; exit 1; }
command -v redis-cli >/dev/null 2>&1 || { echo "❌ Redis is required but not installed. Aborting." >&2; exit 1; }

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
PHP_MAJOR=$(echo $PHP_VERSION | cut -d. -f1)
PHP_MINOR=$(echo $PHP_VERSION | cut -d. -f2)

if [[ $PHP_MAJOR -lt 8 ]]; then
    echo "❌ PHP 8.0 or higher is required. Current version: $PHP_VERSION"
    exit 1
fi

echo "✅ PHP version: $PHP_VERSION"

# Check PHP extensions
echo "🔍 Checking PHP extensions..."
php -m | grep -q pdo || { echo "❌ PHP PDO extension is required"; exit 1; }
php -m | grep -q redis || { echo "❌ PHP Redis extension is required"; exit 1; }
php -m | grep -q openssl || { echo "❌ PHP OpenSSL extension is required"; exit 1; }
php -m | grep -q json || { echo "❌ PHP JSON extension is required"; exit 1; }
php -m | grep -q curl || { echo "❌ PHP cURL extension is required"; exit 1; }

echo "✅ All required PHP extensions are installed"

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Create environment file
if [ ! -f .env ]; then
    echo "📝 Creating environment file..."
    cp .env.example .env
    echo "⚠️  Please edit .env file with your configuration"
else
    echo "✅ Environment file already exists"
fi

# Create logs directory
echo "📁 Creating logs directory..."
mkdir -p logs
chmod 755 logs

# Generate random secrets
echo "🔐 Generating random secrets..."
APP_SECRET=$(openssl rand -hex 32)
JWT_SECRET=$(openssl rand -hex 32)

# Update .env file with generated secrets
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/APP_SECRET=your-secret-key-here/APP_SECRET=$APP_SECRET/" .env
    sed -i '' "s/JWT_SECRET=your-jwt-secret-here/JWT_SECRET=$JWT_SECRET/" .env
else
    # Linux
    sed -i "s/APP_SECRET=your-secret-key-here/APP_SECRET=$APP_SECRET/" .env
    sed -i "s/JWT_SECRET=your-jwt-secret-here/JWT_SECRET=$JWT_SECRET/" .env
fi

echo "✅ Random secrets generated"

# Test database connection
echo "🔍 Testing database connection..."
read -p "Enter database host [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Enter database name [linkshortener]: " DB_NAME
DB_NAME=${DB_NAME:-linkshortener}

read -p "Enter database username: " DB_USER
read -s -p "Enter database password: " DB_PASS
echo

# Update .env with database credentials
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/DB_HOST=localhost/DB_HOST=$DB_HOST/" .env
    sed -i '' "s/DB_NAME=linkshortener/DB_NAME=$DB_NAME/" .env
    sed -i '' "s/DB_USER=username/DB_USER=$DB_USER/" .env
    sed -i '' "s/DB_PASS=password/DB_PASS=$DB_PASS/" .env
else
    # Linux
    sed -i "s/DB_HOST=localhost/DB_HOST=$DB_HOST/" .env
    sed -i "s/DB_NAME=linkshortener/DB_NAME=$DB_NAME/" .env
    sed -i "s/DB_USER=username/DB_USER=$DB_USER/" .env
    sed -i "s/DB_PASS=password/DB_PASS=$DB_PASS/" .env
fi

# Test database connection
mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -e "SELECT 1;" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Database connection successful"
else
    echo "❌ Database connection failed"
    exit 1
fi

# Create database if it doesn't exist
mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" > /dev/null 2>&1
echo "✅ Database created/verified"

# Import database schema
echo "🗄️  Importing database schema..."
mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < database/schema.sql
echo "✅ Database schema imported"

# Test Redis connection
echo "🔍 Testing Redis connection..."
redis-cli ping > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Redis connection successful"
else
    echo "❌ Redis connection failed"
    exit 1
fi

# Set file permissions
echo "🔐 Setting file permissions..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod +x scripts/*.sh
chmod 777 logs/

echo "✅ File permissions set"

# Create systemd service (optional)
read -p "Would you like to create a systemd service for maintenance tasks? (y/n): " CREATE_SERVICE
if [[ $CREATE_SERVICE == "y" || $CREATE_SERVICE == "Y" ]]; then
    echo "📋 Creating systemd service..."
    
    cat > /tmp/linkshortener-cleanup.service << EOF
[Unit]
Description=Link Shortener Cleanup Service
After=network.target

[Service]
Type=oneshot
User=$USER
WorkingDirectory=$(pwd)
ExecStart=/usr/bin/php -f scripts/cleanup.php

[Install]
WantedBy=multi-user.target
EOF

    cat > /tmp/linkshortener-cleanup.timer << EOF
[Unit]
Description=Run Link Shortener Cleanup Daily
Requires=linkshortener-cleanup.service

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
EOF

    echo "⚠️  To install the systemd service, run as root:"
    echo "sudo mv /tmp/linkshortener-cleanup.service /etc/systemd/system/"
    echo "sudo mv /tmp/linkshortener-cleanup.timer /etc/systemd/system/"
    echo "sudo systemctl daemon-reload"
    echo "sudo systemctl enable linkshortener-cleanup.timer"
    echo "sudo systemctl start linkshortener-cleanup.timer"
fi

# Create maintenance script
echo "🔧 Creating maintenance script..."
cat > scripts/cleanup.php << 'EOF'
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use LinkShortener\Services\LinkService;
use LinkShortener\Repositories\LinkRepository;
use LinkShortener\Services\UrlValidatorService;
use LinkShortener\Services\ShortCodeGeneratorService;
use LinkShortener\Services\SecurityService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Setup logger
$logger = new Logger('cleanup');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Create services
$linkRepository = new LinkRepository();
$urlValidator = new UrlValidatorService();
$shortCodeGenerator = new ShortCodeGeneratorService($linkRepository);
$securityService = new SecurityService();
$linkService = new LinkService($linkRepository, $urlValidator, $shortCodeGenerator, $securityService, $logger);

// Run cleanup
$logger->info('Starting cleanup process...');
$count = $linkService->cleanupExpiredLinks();
$logger->info("Cleanup completed. Processed $count expired links.");
EOF

chmod +x scripts/cleanup.php

echo "✅ Maintenance script created"

# Final checks
echo "🔍 Running final checks..."

# Check if web server is configured
if [ -f /etc/apache2/sites-available/000-default.conf ] || [ -f /etc/nginx/sites-available/default ]; then
    echo "⚠️  Don't forget to configure your web server to point to the public/ directory"
fi

# Check if HTTPS is configured
echo "⚠️  For production, make sure to:"
echo "   - Configure HTTPS/SSL"
echo "   - Set APP_ENV=production in .env"
echo "   - Set APP_DEBUG=false in .env"
echo "   - Configure proper file permissions"
echo "   - Set up log rotation"

echo ""
echo "🎉 Installation completed successfully!"
echo ""
echo "Next steps:"
echo "1. Configure your web server to point to the public/ directory"
echo "2. Update .env file with your specific configuration"
echo "3. Test the application by visiting your domain"
echo "4. Set up SSL/HTTPS for production"
echo "5. Configure monitoring and backups"
echo ""
echo "For more information, see the README.md file"
echo ""
echo "🚀 Your Link Shortener is ready to use!"
EOF