# Shared Hosting Setup Guide for Promptash

This guide will help you resolve the database connection issues and properly set up Promptash on your shared hosting environment.

## Current Error Analysis

The error you're seeing indicates two main issues:

1. **Database Access Error**: `SQLSTATE[HY000] [1698] Access denied for user 'promptshub'@'localhost'`
   - The database user doesn't exist or doesn't have proper permissions
   
2. **Fatal Error**: `Call to a member function prepare() on null`
   - The application tries to use database functions when connection failed

## Step-by-Step Solution

### 1. Database Setup on Shared Hosting

Most shared hosting providers use cPanel or similar control panels. Here's how to set up your database:

#### A. Create Database
1. Log into your hosting control panel (cPanel, Plesk, etc.)
2. Find "MySQL Databases" or "Databases" section
3. Create a new database named: `prompts_hub_db`
   - Some hosts prefix with your account name (e.g., `youraccount_prompts_hub_db`)

#### B. Create Database User
1. In the same section, create a new MySQL user
2. Username: `promptshub` (or `youraccount_promptshub` if prefixed)
3. Set a strong password (save this!)

#### C. Assign User to Database
1. Add the user to the database
2. Grant **ALL PRIVILEGES** to the user for this database

#### D. Note Your Database Details
Your hosting provider should show you:
- **Database Host**: Usually `localhost`, but could be `mysql.yourdomain.com`
- **Database Name**: What you created (with any prefixes)
- **Username**: What you created (with any prefixes)
- **Password**: What you set

### 2. Test Database Connection

I've created a test script for you. Follow these steps:

1. **Upload** the `test_db_connection.php` file to your hosting
2. **Edit** the file and update these lines with your actual hosting database details:
   ```php
   $test_host = 'localhost';           // Your DB host
   $test_username = 'promptshub';      // Your DB username (may have prefix)
   $test_password = 'your_password';   // Your DB password
   $test_database = 'prompts_hub_db';  // Your DB name (may have prefix)
   ```
3. **Access** the file in your browser: `https://yoursite.com/test_db_connection.php`
4. **Follow** the troubleshooting tips if there are still errors
5. **Delete** the test file once connection works

### 3. Update Database Configuration

Once the test connection works, update your main configuration:

Edit `config/database.php` with the correct credentials:
```php
<?php
// Database configuration
define('DB_HOST', 'your_actual_host');
define('DB_NAME', 'your_actual_database_name');
define('DB_USER', 'your_actual_username');
define('DB_PASS', 'your_actual_password');
?>
```

### 4. Run Installation

1. **Access** your installation script: `https://yoursite.com/install/index.php`
2. **Follow** the installation wizard
3. **Use the same database credentials** you tested
4. **Create** your admin account
5. **Complete** the installation

### 5. Security Cleanup

After successful installation:
1. **Delete** the `test_db_connection.php` file
2. **Delete** the entire `install/` directory
3. **Set** proper file permissions on `config/database.php` (600 or 644)

## Common Shared Hosting Issues and Solutions

### Issue 1: Username/Database Prefixes
**Problem**: Many shared hosts add your account name as a prefix
**Solution**: 
- If your account is `myaccount`, database becomes `myaccount_prompts_hub_db`
- Username becomes `myaccount_promptshub`

### Issue 2: Different Database Host
**Problem**: Host isn't `localhost`
**Solution**: Check your control panel for the actual MySQL server address

### Issue 3: Permission Errors
**Problem**: User exists but can't access database
**Solution**: 
- Re-assign user to database with ALL PRIVILEGES
- Some hosts require manual permission assignment

### Issue 4: Connection Limits
**Problem**: Too many connections error
**Solution**: This is usually temporary; wait a few minutes and try again

## Files Modified for Better Error Handling

I've updated these files to handle database connection failures gracefully:

1. **`app/models/AppSettings.php`**: Now handles null database connections
2. **`helpers/Database.php`**: Improved error logging and handling
3. **`config/config.php`**: Better initialization error handling
4. **`install/index.php`**: Added install mode flag for proper error reporting

## Testing After Setup

1. **Access your site**: `https://yoursite.com`
2. **Should redirect** to login page (not show errors)
3. **Login** with your admin account
4. **Check** that dashboard loads properly
5. **Test** creating a prompt to verify database functionality

## If You Still Have Issues

1. **Check hosting documentation** for database setup procedures
2. **Contact hosting support** - they can verify database user permissions
3. **Check error logs** in your hosting control panel
4. **Try a different database name/username** if current ones don't work

The key is getting the database credentials exactly right for your specific hosting environment. Each provider has slightly different requirements.