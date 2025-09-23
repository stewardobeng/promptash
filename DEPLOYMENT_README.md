# Promptash - Production Deployment Package

## Quick Setup for Shared Hosting

### 1. Database Setup
```sql
-- Import the complete database schema (includes membership, 2FA, and all features)
mysql -u your_username -p your_database_name < database_complete.sql
```

### 2. Configuration
```bash
# Copy and configure database settings
cp config/database.php.example config/database.php
# Edit config/database.php with your hosting credentials
```

### 3. File Permissions
```bash
# Set proper permissions (if you have shell access)
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 600 config/database.php
```

### 4. Post-Installation
1. Access your domain/install/ to run the installation wizard
2. Create your admin account
3. Delete the install/ directory after setup
4. Configure your app settings in the admin panel

### 5. Important Security Notes
- Ensure mod_rewrite is enabled on your hosting
- The .htaccess file protects sensitive directories
- Never expose config/ or helpers/ directories to web access
- Set display_errors = Off in production

### Support
For detailed setup instructions, see SHARED_HOSTING_COMPLETE_GUIDE.md

---
Package Version: Production Ready v2.0
Build Date: 2025-08-30
Includes: Complete membership system, 2FA, auto-admin premium upgrade