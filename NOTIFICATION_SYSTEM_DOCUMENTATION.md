# Promptash Notification System Documentation

## Overview

The Promptash notification system provides comprehensive notifications for user activities, system events, and administrative actions. It supports both in-app notifications and email notifications using SMTP.

## Features

### ✅ Notification Types

1. **Welcome Notifications**
   - Sent when new users register
   - Includes welcome email and in-app notification

2. **Subscription Notifications**
   - Subscription confirmation (payment successful)
   - Payment due reminders
   - Subscription expiry warnings (7 days before)

3. **Usage Limit Notifications**
   - 75% usage warning
   - 90% usage warning  
   - 100% limit reached
   - Covers prompt creation, AI generation, and category creation

4. **Prompt Sharing Notifications**
   - Notifies recipients when prompts are shared with them
   - Includes link to view shared prompts

5. **Password Reset Notifications**
   - Secure email-based password reset
   - Token-based system with expiry

### ✅ Delivery Channels

1. **In-App Notifications**
   - Real-time notification dropdown in top navigation
   - Dedicated notifications page
   - Badge counts for unread notifications
   - Auto-refresh every 30 seconds

2. **Email Notifications**
   - Professional HTML email templates
   - Configurable SMTP settings
   - Graceful fallback when email is not configured

## Architecture

### Core Components

1. **EmailService** (`helpers/EmailService.php`)
   - Handles SMTP email sending
   - Provides email templates
   - Configuration validation and testing

2. **NotificationService** (`helpers/NotificationService.php`)
   - Manages in-app notifications
   - Coordinates with EmailService
   - Usage limit checking and notification triggers

3. **Database Tables**
   - `user_notifications` - In-app notifications
   - `usage_notifications` - Usage limit tracking

### Integration Points

1. **User Registration** (`helpers/Auth.php`)
   - Sends welcome notifications

2. **Payment Processing** (`helpers/PaymentProcessor.php`)
   - Sends subscription confirmations

3. **Prompt Sharing** (`app/views/api.php`)
   - Sends sharing notifications to recipients

4. **Usage Tracking** (`app/views/dashboard.php`, `prompts.php`)
   - Automatic usage checking and notifications

## Configuration

### SMTP Email Setup

Administrators can configure SMTP settings in **Admin Settings**:

```php
'smtp_host' => 'smtp.gmail.com',
'smtp_port' => 587,
'smtp_username' => 'your-email@gmail.com',
'smtp_password' => 'your-app-password',
'smtp_encryption' => 'tls',
'smtp_from_email' => 'noreply@yourdomain.com',
'smtp_from_name' => 'Promptash',
'email_enabled' => true
```

### Email Templates

The system includes professional HTML email templates for:
- Welcome emails
- Password reset
- Subscription confirmations
- Payment reminders
- Subscription expiry warnings
- Prompt sharing notifications
- Usage limit warnings

## User Interface

### Navigation Dropdown

- Bell icon in top navigation bar
- Real-time unread count badge
- Dropdown list of recent notifications
- Mark as read functionality
- Auto-refresh every 30 seconds

### Notifications Page

Access via `/index.php?page=notifications`:
- Paginated list of all notifications
- Filter by read/unread status
- Mark individual or all as read
- Delete notifications
- Visual indicators for notification types

### Sidebar Integration

- Notifications link in sidebar menu
- Unread count badge on sidebar link

## API Endpoints

### Available Endpoints

1. `get_notifications` - Retrieve user notifications
2. `mark_notification_read` - Mark specific notification as read
3. `mark_all_notifications_read` - Mark all as read
4. `delete_notification` - Delete specific notification
5. `check_usage_notifications` - Manually trigger usage checks

### Usage Examples

```javascript
// Get notifications
fetch('index.php?page=api&action=get_notifications')
  .then(response => response.json())
  .then(data => console.log(data.notifications));

// Mark as read
fetch('index.php?page=api&action=mark_notification_read', {
  method: 'POST',
  body: 'notification_id=123'
});
```

## Automation

### Cron Job Script

Use `cron_notifications.php` for automated processing:

```bash
# Run every hour
0 * * * * /usr/bin/php /path/to/storeprompts/cron_notifications.php
```

**What it does:**
- Checks usage notifications for all users
- Sends subscription expiry warnings
- Cleans up old notifications (30+ days)
- Logs all activities

### Automatic Triggers

1. **Registration** - Welcome notification sent immediately
2. **Payment Success** - Subscription confirmation sent
3. **Page Visits** - Usage checks on dashboard and prompts pages
4. **Usage Actions** - Real-time usage limit monitoring

## Security Features

### Email Security
- Secure password reset tokens with expiry
- No sensitive data in email content
- Rate limiting for password reset requests

### Notification Security
- User isolation (users only see their notifications)
- CSRF protection on API endpoints
- Input validation and sanitization

### Data Privacy
- Automatic cleanup of old notifications
- No personal data in logs
- Secure token generation

## Performance Optimization

### Database Optimization
- Indexed notification queries
- Efficient pagination
- Cleanup of old records

### Frontend Optimization
- Lazy loading of notifications
- Minimal API calls
- Cached notification counts

### Background Processing
- Non-blocking notification sending
- Asynchronous usage checks
- Graceful error handling

## Error Handling

### Email Failures
- Graceful fallback when SMTP not configured
- Error logging without exposing credentials
- Retry logic for temporary failures

### Database Failures
- Fallback to default notification behavior
- Non-blocking notification creation
- Comprehensive error logging

## Monitoring and Logs

### Log Files
- `logs/cron_notifications.log` - Automated processing logs
- PHP error logs for debugging

### Health Checks
- SMTP connection testing
- Notification delivery verification
- Usage tracking validation

## Customization

### Adding New Notification Types

1. **Add to EmailService** templates
2. **Extend NotificationService** methods
3. **Update UI** for new notification types
4. **Add API endpoints** if needed

### Email Template Customization

Templates are in `EmailService.php`:
- HTML-based with inline CSS
- Responsive design
- Brand-consistent styling

### Notification Categories

Current types:
- `welcome`
- `subscription_confirmation`
- `subscription_expiry`
- `payment_reminder`
- `prompt_shared`
- `usage_warning`
- `limit_reached`

## Troubleshooting

### Common Issues

1. **Emails not sending**
   - Check SMTP configuration
   - Verify credentials
   - Test with `test_email_configuration`

2. **Notifications not appearing**
   - Check JavaScript console for errors
   - Verify API endpoints
   - Check database tables

3. **Performance issues**
   - Monitor cron job execution
   - Check database query performance
   - Review log files

### Debug Mode

Enable debug logging:
```php
error_log("Notification debug: " . json_encode($data));
```

## Migration and Deployment

### Database Requirements
- MySQL 5.7+ or MariaDB 10.2+
- Tables created via `database_complete.sql`

### File Permissions
- `logs/` directory writable
- Cron script executable
- Email service accessible

### Production Checklist
- [ ] SMTP credentials configured
- [ ] Cron job scheduled
- [ ] Error logging enabled
- [ ] Email testing completed
- [ ] Performance monitoring active

## Support and Maintenance

### Regular Tasks
- Monitor notification delivery rates
- Clean up old notifications
- Update email templates
- Review error logs

### Updates and Patches
- Test email functionality after updates
- Verify notification triggers
- Check API endpoint compatibility

---

**Last Updated:** August 31, 2025
**Version:** 1.0.0
**Documentation by:** StorePrompts Development Team