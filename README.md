# 🔗 LinkShortener API - Enhanced Partner System

A comprehensive URL shortening platform with partner integration, revenue sharing, and advanced analytics. Features a complete admin panel for managing all aspects of the system without requiring backend access.

## ✨ Features

### 🎯 **Core Functionality**
- **URL Shortening**: Generate short, branded links with custom aliases
- **Partner System**: Complete partner onboarding and management
- **Revenue Sharing**: CPM-based revenue distribution with detailed tracking
- **Advertisement Integration**: Interstitial ads with configurable durations
- **Analytics**: Comprehensive click tracking and performance metrics
- **API Access**: RESTful API with OAuth 2.0 and API key authentication

### 🛡️ **Security & Management**
- **Admin Panel**: Holistic web-based administration interface
- **Partner Blacklisting**: Automated and manual partner blocking
- **Rate Limiting**: Configurable API request limits
- **Multi-Factor Authentication**: TOTP and SMS-based 2FA
- **Audit Logging**: Complete administrative action tracking
- **Fraud Detection**: Advanced click and revenue fraud prevention

### 📊 **Analytics & Reporting**
- **Real-time Dashboard**: Live metrics and performance charts
- **Partner Analytics**: Individual partner performance tracking
- **Revenue Reports**: Detailed financial reporting and forecasting
- **Advertisement Analytics**: Campaign performance optimization
- **Export Capabilities**: CSV, PDF, and Excel report generation

## 🚀 **One-Click Automated Deployment**

### Prerequisites
- Linux, macOS, or Windows with WSL2
- Internet connection for downloading dependencies
- At least 4GB RAM and 10GB disk space

### 🎯 **Super Simple Deployment**

Just run this single command and everything will be set up automatically:

```bash
chmod +x deploy.sh && ./deploy.sh
```

**That's it!** The script will:
- ✅ Detect your operating system
- ✅ Install Docker and Docker Compose automatically
- ✅ Generate SSL certificates
- ✅ Create all configuration files
- ✅ Set up the database with sample data
- ✅ Configure Redis caching
- ✅ Start all services
- ✅ Provide you with access information

### 📋 **What Gets Deployed**

The automated deployment creates:

#### **🐳 Docker Containers**
- **Application Server**: PHP 8.2 + Apache with all extensions
- **MySQL Database**: Optimized database with complete schema
- **Redis Cache**: High-performance caching layer
- **phpMyAdmin**: Database administration interface
- **RedisInsight**: Redis administration interface

#### **🔧 Services & Features**
- **Admin Panel**: Complete administrative interface
- **API Endpoints**: RESTful API with authentication
- **Health Monitoring**: System health checks and metrics
- **Automated Backups**: Daily database and file backups
- **Cron Jobs**: Automated maintenance and cleanup tasks
- **SSL/HTTPS**: Self-signed certificates (production-ready)

## 🌐 **Access Your Application**

After deployment completes, you can access:

### **🏠 Main Application**
- **HTTP**: http://localhost
- **HTTPS**: https://localhost

### **👨‍💼 Admin Panel**
- **URL**: http://localhost/admin
- **Username**: `admin`
- **Password**: `password`
- **Features**: Complete system management without backend access

### **🗄️ Database Management**
- **phpMyAdmin**: http://localhost:8081
- **Credentials**: Provided in deployment output

### **🔄 Cache Management**
- **RedisInsight**: http://localhost:8082
- **Connection**: Automatic setup included

## 🛠️ **Management Commands**

### **📊 Monitor Services**
```bash
# View running services
docker-compose ps

# View logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app
```

### **🔄 Service Management**
```bash
# Restart all services
docker-compose restart

# Stop all services
docker-compose down

# Update and restart
docker-compose build --no-cache && docker-compose up -d
```

### **💾 Backup & Restore**
```bash
# Backup database
docker-compose exec mysql mysqldump -u linkshortener -p linkshortener_api > backup.sql

# Access application container
docker-compose exec app bash

# View application logs
docker-compose logs -f app
```

## 🎛️ **Admin Panel Features**

### **📊 Dashboard**
- Real-time system metrics and health monitoring
- Revenue trends and performance charts
- Partner activity and top-performing advertisements
- Security alerts and system notifications

### **👥 Partner Management**
- Partner status control (activate, suspend, ban)
- Revenue share adjustment per partner
- Permission management and access control
- Detailed partner activity tracking

### **📺 Advertisement Management**
- Campaign creation with media upload support
- Targeting options (geographic, demographic, behavioral)
- Budget control and performance analytics
- A/B testing and optimization tools

### **⚙️ System Configuration**
- **Advertisement Settings**: Duration, skip options, targeting, fraud protection
- **Revenue Management**: Revenue share, payout settings, currency support
- **Security Configuration**: Rate limiting, MFA, authentication requirements
- **Link Management**: Custom aliases, bulk operations, QR codes, domain filtering
- **Maintenance Settings**: Auto cleanup, data retention, backups, health monitoring

### **🔐 Security Management**
- Security event monitoring and alerts
- Partner and domain blacklist management
- Audit log tracking and analysis
- Fraud detection and prevention

## 🔧 **Configuration**

### **Environment Variables**
All configuration is managed through the `.env` file created during deployment:

```bash
# Edit configuration
nano .env

# Restart to apply changes
docker-compose restart
```

### **Key Settings to Update**
- **Stripe API Keys**: For payment processing
- **SMTP Settings**: For email functionality
- **Domain Settings**: For production deployment
- **SSL Certificates**: Replace self-signed certificates for production

## 📚 **API Documentation**

### **Authentication**
```bash
# API Key Authentication
curl -H "Authorization: Bearer YOUR_API_KEY" http://localhost/api/v1/links

# OAuth 2.0 (for partners)
curl -H "Authorization: Bearer YOUR_ACCESS_TOKEN" http://localhost/api/v1/analytics
```

### **Create Short Link**
```bash
curl -X POST http://localhost/api/v1/links \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"url": "https://example.com", "custom_alias": "my-link"}'
```

### **Get Analytics**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/api/v1/analytics?period=30d
```

## 🏗️ **Architecture**

### **Technology Stack**
- **Backend**: PHP 8.2, Apache, MySQL 8.0, Redis 7
- **Frontend**: Bootstrap 5, Chart.js, Twig templating
- **Infrastructure**: Docker, Docker Compose
- **Monitoring**: Health checks, metrics, logging

### **Security Features**
- **Encryption**: All sensitive data encrypted at rest and in transit
- **Authentication**: Multi-factor authentication, API keys, OAuth 2.0
- **Rate Limiting**: Configurable request limits per partner
- **Audit Logging**: Complete administrative action tracking
- **Fraud Detection**: Advanced click and revenue fraud prevention

## 📖 **Documentation**

Comprehensive documentation is included:
- **API Documentation**: `API_DOCUMENTATION.md`
- **Service Flows**: `SERVICE_FLOWS_DOCUMENTATION.md`
- **Architecture**: `ENHANCED_ARCHITECTURE_DOCUMENTATION.md`
- **Admin Panel**: `ADMIN_PANEL_DOCUMENTATION.md`

## 🔒 **Security Best Practices**

### **Immediate Actions After Deployment**
1. **Change Default Password**: Update admin password immediately
2. **Update API Keys**: Configure Stripe and other service API keys
3. **Configure SMTP**: Set up email service for notifications
4. **SSL Certificates**: Replace self-signed certificates for production
5. **Firewall Rules**: Configure appropriate firewall rules

### **Production Considerations**
- Use environment-specific `.env` files
- Implement proper SSL certificates (Let's Encrypt)
- Configure monitoring and alerting
- Set up automated backups to external storage
- Review and update security settings regularly

## 🚨 **Troubleshooting**

### **Common Issues**

#### **Services Won't Start**
```bash
# Check Docker status
docker --version
docker-compose --version

# Check logs
docker-compose logs

# Rebuild containers
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

#### **Permission Issues**
```bash
# Fix permissions
sudo chown -R $USER:$USER .
chmod -R 755 storage logs
```

#### **Database Connection Issues**
```bash
# Check MySQL container
docker-compose logs mysql

# Reset database
docker-compose down -v
docker-compose up -d
```

## 📞 **Support**

### **Getting Help**
- Check the comprehensive documentation files
- Review Docker Compose logs for error details
- Ensure all prerequisites are met
- Verify firewall and network settings

### **System Requirements**
- **Minimum**: 2GB RAM, 5GB disk space
- **Recommended**: 4GB RAM, 20GB disk space
- **Production**: 8GB+ RAM, 50GB+ disk space

## 🎉 **Success!**

Your LinkShortener API is now fully deployed and ready to use! The system includes:

✅ **Complete Partner Management System**  
✅ **Revenue Sharing with Analytics**  
✅ **Advertisement Campaign Management**  
✅ **Comprehensive Admin Panel**  
✅ **RESTful API with Authentication**  
✅ **Real-time Monitoring and Health Checks**  
✅ **Automated Backups and Maintenance**  
✅ **Security Features and Fraud Protection**  

**No manual configuration required** - everything is set up and ready to go!

---

**Happy Link Shortening! 🚀**
