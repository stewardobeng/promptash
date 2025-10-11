# 🚀 Complete Shared Hosting Deployment Guide

## ✅ **What's Fixed in This Version**

### **Database Issues Resolved:**
- ✅ Complete database schema with all required tables
- ✅ Two-Factor Authentication tables included
- ✅ Membership system with Free and Premium tiers  
- ✅ Usage tracking and payment processing tables
- ✅ Auto-upgrade triggers for admin users to premium
- ✅ Proper foreign key relationships and indexes

### **Configuration Issues Resolved:**
- ✅ Removed local-only database configuration
- ✅ Created database template with examples
- ✅ Fixed session conflicts in upgrade.php
- ✅ Updated installation script to use complete schema
- ✅ Included all required app settings

### **Admin Premium Membership:**
- ✅ Database triggers automatically upgrade admin users to premium
- ✅ Auth helper ensures admin users get premium tier on login
- ✅ Upgrade scripts available for manual user upgrades
- ✅ Web-based upgrade tool for easy management

## 📦 **Installation Steps for Shared Hosting**

### **Step 1: Upload Files**
1. Extract the deployment package to your hosting public folder
2. Ensure all files have proper permissions (644 for files, 755 for directories)

### **Step 2: Database Setup**
```sql
-- Create your database in your hosting control panel
-- Then import the complete schema:
mysql -u your_username -p your_database_name < database_complete.sql
```

### **Step 3: Configuration**
```bash
# Copy the database template
cp config/database.php.example config/database.php

# Edit config/database.php with your hosting credentials:
define('DB_HOST', 'your_host');
define('DB_NAME', 'your_database_name');  
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### **Step 4: Installation Wizard**
1. Go to: `https://yourdomain.com/install/`
2. Follow the installation wizard
3. Create your admin account
4. **IMPORTANT:** Delete the `/install/` directory after installation

### **Step 5: Verify Admin Premium Status**
Your admin account will automatically have premium membership. You can verify this by:
1. Login to your admin account
2. Check the navbar shows "Premium" badge
3. Go to Settings → View current plan status

## 🔧 **Post-Installation Configuration**

### **Required Settings (Admin Panel):**
1. **App Settings** - Configure application name and description
2. **Payment Integration** - Add Paystack keys if using payments
3. **AI Features** - Add OpenRouter API key if using AI features

### **Security Checklist:**
- ✅ Delete `/install/` directory
- ✅ Verify `.htaccess` file is protecting sensitive directories
- ✅ Ensure `config/database.php` has correct permissions (600 or not web-accessible)
- ✅ Enable HTTPS with SSL certificate
- ✅ Set PHP `display_errors = Off` for production

## 🎯 **Features Ready Out-of-the-Box**

### **User Management:**
- ✅ User registration and authentication
- ✅ Two-Factor Authentication (2FA) support
- ✅ Admin and user role management
- ✅ Password reset functionality

### **Membership System:**
- ✅ Personal Plan: 50 prompts/month, 10 AI generations, 5 categories
- ✅ Premium Plan: Unlimited prompts, 500 AI generations, unlimited categories
- ✅ Usage tracking and limit enforcement
- ✅ Auto-renewal and payment processing ready

### **Prompt Management:**
- ✅ Create, edit, delete prompts
- ✅ Category organization
- ✅ Search and filtering
- ✅ Favorites and usage tracking
- ✅ Backup and restore functionality

### **Admin Features:**
- ✅ User management dashboard
- ✅ System statistics and analytics
- ✅ Membership management
- ✅ Application settings configuration
- ✅ Backup and maintenance tools

## 🛠 **Upgrade Existing Users to Premium**

### **Web Interface (Recommended):**
```
https://yourdomain.com/scripts/upgrade_user_premium.php
```

### **Command Line:**
```bash
cd /path/to/your/website/scripts
php upgrade_user_premium.php user@example.com
```

## 🆘 **Troubleshooting Common Issues**

### **Database Connection Errors:**
- Verify database credentials in `config/database.php`
- Check if your hosting allows remote database connections
- Ensure database exists and user has proper permissions

### **Installation Wizard Not Working:**
- Check file permissions (install directory needs to be writable)
- Verify PHP version (requires PHP 7.4+)
- Check error logs for specific issues

### **Admin Not Getting Premium:**
- Check if membership tables exist in database
- Run the admin upgrade script manually
- Verify triggers are created in database

### **Payment Issues:**
- Add Paystack API keys in App Settings
- Verify webhook URLs are properly configured
- Check payment processor configuration

### **Theme/Styling Issues:**
- Clear browser cache (Ctrl+Shift+R)
- Verify all CSS/JS files uploaded correctly
- Check .htaccess for asset serving rules

## 📞 **Support**

### **Files to Check for Errors:**
- Web server error logs
- PHP error logs  
- Database error logs
- Application logs in `logs/` directory (if exists)

### **Useful Scripts:**
- `scripts/upgrade_user_premium.php` - Upgrade users to premium
- `scripts/upgrade_admin_users.php` - Upgrade all admins to premium
- `scripts/migrate.php` - Database migrations
- `scripts/fix_user_tiers.php` - Fix user tier assignments

### **Configuration Files:**
- `config/database.php` - Database connection
- `config/config.php` - Application configuration
- `.htaccess` - Web server configuration

---

## 🎉 **You're All Set!**

Your Promptash installation should now be fully functional with:
- ✅ Complete database schema
- ✅ Admin with premium membership
- ✅ All features working
- ✅ Ready for production use

Access your application at: `https://yourdomain.com`

**First Login:** Use the admin credentials you created during installation.

---
*This guide ensures your application works perfectly on shared hosting environments.*
