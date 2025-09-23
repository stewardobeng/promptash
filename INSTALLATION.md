# Promptash - Installation Guide

## Quick Start

1. **Upload Files**: Extract and upload all files to your web server
2. **Visit Your Site**: Navigate to your domain (or `/install/`) and complete **Step 0** to install Composer dependencies if requested
3. **Follow Wizard**: Continue through the installation wizard (database + admin account)
4. **Start Using**: Login and start managing your prompts!

## Detailed Installation Steps

### Step 1: Server Requirements
Ensure your server meets these requirements:
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache or Nginx
- **Extensions**: PDO, PDO_MySQL, mbstring, json

### Step 2: Upload Files
1. Extract the `promptash-v1.0.0.zip` file
2. Upload all contents to your web server's document root
3. Ensure file permissions are set correctly:
   ```bash
   chmod 755 promptash/
   chmod 644 promptash/*.php
   chmod 644 promptash/.htaccess
   ```


### Step 3: Install Dependencies (Composer)
If you do not have SSH access, use the built-in dependency installer before continuing.

1. Open `/install/` — the wizard presents **Step 0: Install Dependencies**.
2. Click **Run Composer Install** to execute the bundled `composer.phar` and download vendor packages.
3. If PHP process functions are disabled, edit `composer-install.php`, update `SECRET_KEY`, then open `composer-install.php?key=YOUR_SECRET` in your browser.
4. Delete `composer-install.php` after the dependencies finish installing.

### Step 4: Database Setup
1. Create a new MySQL database
2. Create a database user with full privileges on the database
3. Note down the database details (host, name, username, password)

### Step 5: Run Installation Wizard
1. Open your web browser
2. Navigate to your domain (e.g., `https://yourdomain.com`)
3. You'll be automatically redirected to the installation wizard
4. Follow these steps:

#### Database Configuration
- **Database Host**: Usually `localhost`
- **Database Name**: The name of your MySQL database
- **Database Username**: Your MySQL username
- **Database Password**: Your MySQL password

#### Admin Account Setup
- **First Name**: Your first name
- **Last Name**: Your last name
- **Username**: Choose a unique username
- **Email**: Your email address
- **Password**: Choose a strong password (minimum 8 characters)

#### Complete Installation
- Click "Install Promptash"
- Wait for the installation to complete
- You'll be redirected to the login page

### Step 6: First Login
1. Use your admin credentials to login
2. Explore the dashboard
3. Create your first prompt
4. Set up categories to organize your prompts
5. Configure SMTP settings for email notifications (optional)

## Post-Installation

### Optional: Email Notifications Setup
To enable email notifications (welcome emails, password resets, prompt sharing):

1. **Access Admin Settings**:
   - Login as admin
   - Go to Settings page
   - Scroll to "Email Configuration" section

2. **Configure SMTP Settings**:
   - Enable email notifications checkbox
   - Enter SMTP host (e.g., smtp.gmail.com)
   - Set port (587 for TLS, 465 for SSL)
   - Enter username and password
   - Set encryption method (TLS recommended)
   - Configure from email and name

3. **Test Configuration**:
   - Use the "Test Email Configuration" button
   - Verify test email is received

4. **Common SMTP Providers**:
   - **Gmail**: smtp.gmail.com, port 587, TLS, App Password required
   - **Outlook**: smtp-mail.outlook.com, port 587, TLS
   - **Yahoo**: smtp.mail.yahoo.com, port 587, TLS

**Note**: Email notifications work without SMTP (in-app only), but SMTP enables email delivery for password resets and user communications.

### Security Recommendations
1. **Delete Install Directory**: Remove the `/install/` folder after installation
2. **Update Passwords**: Change default passwords if any
3. **Enable HTTPS**: Use SSL certificates for secure connections
4. **Regular Backups**: Set up automated database backups

### Configuration
Edit `config/config.php` to customize:
- Session timeout
- Password requirements
- File upload limits
- Application settings

## Troubleshooting

### Common Issues

**"Database connection failed"**
- Verify database credentials
- Check if MySQL service is running
- Ensure database user has proper permissions

**"Permission denied" errors**
- Check file permissions
- Ensure web server can read/write files
- Verify directory ownership

**"Page not found" errors**
- Check if mod_rewrite is enabled (Apache)
- Verify .htaccess file is present
- Check web server configuration

**Styling not loading**
- Clear browser cache
- Check if CSS files are accessible
- Verify file paths

### Getting Help
1. Check the README.md file
2. Review server error logs
3. Verify system requirements
4. Check file permissions

## Manual Installation (Advanced)

If the automatic installer doesn't work:

1. **Create Database Tables**:
   ```sql
   -- Import the complete database schema
   mysql -u username -p database_name < database_complete.sql
   ```

   *Note: The `database_complete.sql` file contains all tables, indexes, and default data needed for the application, including the complete notification system with SMTP email configuration.*

2. **Create Database Config**:
   ```php
   // Create config/database.php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ?>
   ```

3. **Create Admin User**:
   ```sql
   INSERT INTO users (username, email, password, first_name, last_name, role) 
   VALUES ('admin', 'admin@example.com', '$2y$10$hash', 'Admin', 'User', 'admin');
   ```

## Upgrading

To upgrade to a newer version:
1. Backup your database and files
2. Upload new files (don't overwrite config/database.php)
3. Run any database migrations if provided
4. Clear cache and test functionality

---

**Need Help?** Check the README.md file for more detailed information.


