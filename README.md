# Promptash

A professional PHP web application for managing and organizing AI prompts with a modern, responsive interface.

## Features

### Core Functionality
- **User Management**: Registration, login, and profile management
- **Prompt Management**: Create, edit, delete, and organize prompts
- **Categories**: Organize prompts into custom categories
- **Search & Filter**: Full-text search and category filtering
- **Favorites**: Mark important prompts as favorites
- **Usage Tracking**: Track how often prompts are used

### Admin Features
- **Admin Panel**: Comprehensive dashboard with statistics
- **User Management**: View, edit, and manage all users
- **System Information**: Monitor application health and usage

### Technical Features
- **Installation Wizard**: Easy setup with database configuration
- **Responsive Design**: Works perfectly on desktop and mobile
- **Professional UI/UX**: Modern gradient design with smooth animations
- **Security**: Password hashing, SQL injection protection, session management
- **Database**: MySQL with optimized schema and indexes

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- mod_rewrite enabled (for Apache)

### Quick Installation
1. Upload all files to your web server
2. Visit your domain (or `/install/`) in a web browser. The wizard begins with **Step 0** to install Composer dependencies when they are missing.
3. Follow the installation wizard:
   - Run the dependency installer if prompted (Composer executes via the bundled `composer.phar` so SSH access is not required)
   - Enter database connection details
   - Create your admin account
   - Complete the installation


### Installing Dependencies on Shared Hosting

Most shared hosts block SSH access. Promptash ships with a browser-based Composer runner so you can install PHP dependencies without the command line.

1. Browse to `/install/` and complete **Step 0: Install Dependencies**.
2. If PHP process functions are disabled, edit `composer-install.php`, change `SECRET_KEY`, then open `composer-install.php?key=YOUR_SECRET` in your browser.
3. Delete `composer-install.php` after the dependencies finish installing.

Once dependencies are installed, continue with the database and admin account steps.

### Manual Installation
1. Create a MySQL database
2. Import the complete database schema:
   ```bash
   mysql -u username -p database_name < database_complete.sql
   ```
3. Copy `config/database.php.example` to `config/database.php`
4. Edit database configuration in `config/database.php`
5. Create an admin user manually in the database

*Note: The `database_complete.sql` file contains the complete schema with all tables, indexes, and default settings for fresh installations.*

## File Structure

```
promptash/
├── app/
│   ├── controllers/          # Application controllers
│   ├── models/              # Database models
│   └── views/               # View templates
├── assets/
│   ├── css/                 # Stylesheets
│   ├── js/                  # JavaScript files
│   └── images/              # Image assets
├── config/
│   ├── config.php           # Main configuration
│   ├── database.php         # Database configuration (created during install)
│   └── database.sql         # Original database schema
├── helpers/
│   ├── Auth.php             # Authentication helper
│   └── Database.php         # Database helper
├── install/
│   └── index.php            # Installation wizard
├── .htaccess                # Apache rewrite rules
├── database_complete.sql    # Complete database schema for fresh installs
├── index.php                # Main application entry point
└── README.md                # This file
```

## Configuration

### Database Configuration
The database configuration is automatically created during installation. If you need to modify it manually, edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Application Settings
Modify settings in `config/config.php`:
- Session lifetime
- Password requirements
- File upload limits
- Pagination settings

## Usage

### For Users
1. **Register**: Create an account or login with existing credentials
2. **Create Prompts**: Add new prompts with titles, descriptions, and content
3. **Organize**: Use categories and tags to organize your prompts
4. **Search**: Find prompts quickly using the search functionality
5. **Manage**: Edit, delete, or mark prompts as favorites

### For Administrators
1. **Access Admin Panel**: Navigate to Admin Panel from the sidebar
2. **View Statistics**: Monitor user activity and system usage
3. **Manage Users**: View, edit, or delete user accounts
4. **System Monitoring**: Check application health and performance

## Security Features

- **Password Hashing**: All passwords are hashed using PHP's password_hash()
- **SQL Injection Protection**: All database queries use prepared statements
- **Session Management**: Secure session handling with timeout
- **Input Validation**: Server-side validation for all user inputs
- **CSRF Protection**: Forms include CSRF token validation
- **File Access Control**: Sensitive files are protected via .htaccess

## Customization

### Themes
The application supports custom themes. Modify `assets/css/style.css` to change:
- Color schemes
- Typography
- Layout spacing
- Animation effects

### Adding Features
1. Create new models in `app/models/`
2. Add controllers in `app/controllers/`
3. Create views in `app/views/`
4. Update routing in `index.php`

## Troubleshooting

### Common Issues

**Installation fails with database error**
- Check database credentials
- Ensure database exists and user has proper permissions
- Verify MySQL version compatibility

**Pages not loading correctly**
- Check if mod_rewrite is enabled
- Verify .htaccess file is present
- Check file permissions

**Styling issues**
- Clear browser cache
- Check if CSS files are accessible
- Verify file paths in layout.php

**Session issues**
- Check PHP session configuration
- Verify session directory permissions
- Check session timeout settings

### Error Logs
Check your web server error logs for detailed error information:
- Apache: `/var/log/apache2/error.log`
- Nginx: `/var/log/nginx/error.log`
- PHP: Check `error_log` setting in php.ini

## Performance Optimization

### Database
- Indexes are already optimized for common queries
- Consider adding more indexes for custom queries
- Regular database maintenance and optimization

### Caching
- Enable PHP OPcache for better performance
- Consider implementing Redis for session storage
- Use CDN for static assets in production

### Web Server
- Enable gzip compression
- Set proper cache headers for static files
- Use HTTP/2 if available

## Security Recommendations

### Production Deployment
1. **Disable Error Display**: Set `display_errors = Off` in php.ini
2. **Remove Install Directory**: Delete `/install/` and `composer-install.php` after installation
3. **Update Regularly**: Keep PHP and MySQL updated
4. **Use HTTPS**: Always use SSL certificates in production
5. **Backup Regularly**: Implement automated database backups

### File Permissions
```bash
# Recommended file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 600 config/database.php
```

## Support

For support and questions:
1. Check this README file
2. Review the troubleshooting section
3. Check server error logs
4. Verify system requirements

## License

This project is open source and available under the MIT License.

## Version History

### v1.0.0
- Initial release
- Complete prompt management system
- Admin panel
- Responsive design
- Installation wizard

---

**Promptash** - Professional prompt management made simple.

