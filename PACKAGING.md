# Promptash - Packaging Guide

## Overview
This guide will help you package the Promptash application for distribution to others.

## Database Schema
The application now uses a single, comprehensive database schema file:
- **`database_complete.sql`** - Contains all tables, indexes, and default data for fresh installations

## Files to Include in Package

### Core Application Files
```
✅ Include these files:
app/                     # Application logic
assets/                  # CSS, JS, and other assets
config/                  # Configuration files (except database.php)
helpers/                 # Helper classes
install/                 # Installation wizard
.htaccess               # Apache configuration
index.php               # Main application entry point
database_complete.sql   # Complete database schema
README.md               # Documentation
INSTALLATION.md         # Installation guide
```

### Files to Exclude from Package
```
❌ Remove these files before packaging:
config/database.php     # Contains actual database credentials
add_ai_settings.sql     # Legacy migration file (merged into database_complete.sql)
add_app_settings.sql    # Legacy migration file (merged into database_complete.sql)
migrate_2fa.php         # Legacy migration script
*.log                   # Any log files
.env                    # Environment files if any
.git/                   # Git repository files
```

## Pre-Packaging Checklist

### 1. Clean Up Development Files
```bash
# Remove development-specific files
rm -f config/database.php
rm -f add_ai_settings.sql
rm -f add_app_settings.sql
rm -f migrate_2fa.php
rm -rf .git/
```

### 2. Verify File Permissions
```bash
# Set appropriate permissions for distribution
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 755 install/
```

### 3. Test Installation
1. Create a fresh database
2. Import `database_complete.sql`:
   ```sql
   mysql -u username -p database_name < database_complete.sql
   ```
3. Verify all tables are created correctly
4. Test the installation wizard

### 4. Validate Configuration
- Ensure `config/config.php` has appropriate default settings
- Verify that the installation wizard works properly
- Test that all features work with the new schema

## Creating the Distribution Package

### Option 1: ZIP Archive
```bash
# Create a clean package
zip -r promptash-v1.0.0.zip . \
  -x "config/database.php" \
  -x "add_*.sql" \
  -x "migrate_*.php" \
  -x ".git/*" \
  -x "*.log"
```

### Option 2: Manual Package
1. Create a new directory: `promptash-v1.0.0/`
2. Copy all required files (see include list above)
3. Exclude unwanted files (see exclude list above)
4. Create ZIP/TAR archive

## Installation Instructions for End Users

Include these instructions with your package:

### Quick Start
1. Extract the package to your web server
2. Create a MySQL database
3. Visit your domain in a browser
4. Follow the installation wizard

### Manual Installation
1. Extract files to web server
2. Import database schema:
   ```bash
   mysql -u username -p database_name < database_complete.sql
   ```
3. Create `config/database.php` with your credentials
4. Access the application

## Version Information
- **Database Schema Version**: 1.0.0 (Complete)
- **Application Version**: 1.0.0
- **PHP Requirements**: 7.4+
- **MySQL Requirements**: 5.7+

## Security Notes for Distribution
1. **Default Credentials**: No default admin account is created
2. **File Permissions**: Set restrictive permissions in production
3. **HTTPS**: Recommend HTTPS for production deployments
4. **Directory Protection**: Remove `/install/` after installation

## Support Information
Include in your package documentation:
- System requirements
- Installation troubleshooting
- Basic configuration guide
- Security recommendations

---

**Package Checklist:**
- [ ] Removed development files
- [ ] Verified database schema completeness
- [ ] Tested installation wizard
- [ ] Set appropriate file permissions
- [ ] Created distribution archive
- [ ] Prepared documentation