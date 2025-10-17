# Authentication System Guide - Gate Codes v1.1.6

## 🔐 Overview

The Gate Codes system now uses a **role-based authentication system** with two types of users:

- **User**: Can search gate codes and submit contributions (public access)
- **Admin**: Full access to admin dashboard and all management features

## 🚀 Quick Start

### For Admins

1. **Login to Admin Panel**:
   - Navigate to: `http://localhost/searchgatecode/public/admin/login.php`
   - Enter your 5-digit PIN
   - Click "Login to Dashboard"

2. **Forgot Your PIN?**:
   - Click "Forgot your PIN?" on login page
   - Enter your registered email address
   - Check your inbox for recovery email
   - Use the PIN from the email to login

3. **Logout**:
   - Click the red "Logout" button at the bottom of the sidebar
   - Your session will be securely destroyed

### For Users

1. **Access Public Search**:
   - Navigate to: `http://localhost/searchgatecode/public/index.php`
   - Enter your PIN to access the search interface
   - Search communities and contribute updates

2. **Users cannot access the admin dashboard** (role restriction)

## 👥 User Management

### Adding New Users

1. Login as admin
2. Go to **Users** page
3. Click **"+ Add New User"**
4. Fill in the form:
   - **Name**: User's full name (required)
   - **Email**: Valid email address (required, unique)
   - **Role**: Select "User" or "Admin"
   - **PIN**: Auto-generated (5 digits) or click "Generate"
5. Click **"Save User"**

### User Roles Explained

| Role | Access Level | Permissions |
|------|-------------|-------------|
| **User** | Public only | - Search gate codes<br>- Submit contributions<br>- Update photos<br>- Report issues |
| **Admin** | Full access | - All User permissions<br>- Access admin dashboard<br>- Manage communities<br>- Review contributions<br>- Manage users<br>- System settings<br>- Backups |

### Editing Users

1. Go to **Users** page
2. Click **"Edit"** on any user
3. Update fields (name, email, role, PIN)
4. Click **"Save User"**

**Note**: Changing a user's role from Admin to User will revoke their dashboard access.

### Deleting Users

1. Go to **Users** page
2. Click **"Delete"** on the user
3. Confirm deletion
4. User's access is immediately revoked

## 📧 Email Recovery System

### Setup Requirements

1. **Install PHPMailer**:
   ```bash
   cd c:\xampp\htdocs\searchgatecode
   composer require phpmailer/phpmailer
   ```

2. **Configure SMTP Settings**:
   - Edit: `public/admin/includes/email_config.php`
   - Set your SMTP credentials:
     ```php
     define('SMTP_HOST', 'smtp.gmail.com');
     define('SMTP_PORT', 587);
     define('SMTP_USERNAME', 'your-email@gmail.com');
     define('SMTP_PASSWORD', 'your-app-password');
     ```

3. **Set Administrator Email**:
   - Login to admin dashboard
   - Go to **Settings** page
   - Enter your email in "Administrator Email" field
   - Click "Save Settings"

### For Gmail Users

Gmail requires an **App Password** (not your regular password):

1. Enable 2-Factor Authentication on your Google account
2. Go to: https://myaccount.google.com/apppasswords
3. Generate a new App Password
4. Use the 16-character code in `email_config.php`

### Using PIN Recovery

1. Go to: `http://localhost/searchgatecode/public/admin/forgot-pin.php`
2. Enter your registered email address
3. Click "Send Recovery Email"
4. Check your inbox (and spam folder)
5. You'll receive an email with:
   - Your name
   - Your PIN code
   - Security notice
6. Use the PIN to login

### Email Template

The recovery email includes:
- Professional design with branding
- Large, easy-to-read PIN display
- Security warning
- Responsive layout for mobile

## 🔒 Security Features

### Session Security
- PHP sessions for authentication
- Automatic session timeout
- Secure cookie handling
- Session destruction on logout

### Role Verification
- Every admin page verifies role
- Automatic redirect if unauthorized
- Session-based role storage
- Real-time role checking

### Email Security
- Unique email constraint
- Email validation (FILTER_VALIDATE_EMAIL)
- SMTP TLS/SSL encryption
- No password storage in emails

### PIN Security
- 5-digit unique PINs
- Memorable pattern generation
- Duplicate prevention
- Masked display (password input)

## 🔄 Migration from Old System

### Backward Compatibility

The old `?key=43982` parameter still works for backward compatibility:

```
http://localhost/.../admin/index.php?key=43982
```

However, **it's recommended to use the new login system**:

```
http://localhost/.../admin/login.php
```

### Updating Existing Users

All existing users need to be updated with email and role:

1. Go to **Users** page
2. Click **"Edit"** on each user
3. Add their email address
4. Set their role (User or Admin)
5. Click **"Save User"**

## 📋 Common Tasks

### Creating an Admin User

```php
// In data/pin.json
{
    "name": "John Doe",
    "pin": "12345",
    "email": "john@example.com",
    "role": "admin",
    "date": "2025-01-15 10:30:00"
}
```

### Creating a Regular User

```php
// In data/pin.json
{
    "name": "Jane Smith",
    "pin": "54321",
    "email": "jane@example.com",
    "role": "user",
    "date": "2025-01-15 10:35:00"
}
```

### Testing Email Recovery

1. Make sure PHPMailer is installed
2. Configure SMTP in email_config.php
3. Add administrator email in Settings
4. Go to forgot-pin.php
5. Enter a registered email
6. Check inbox for recovery email

## 🛠️ Troubleshooting

### Cannot Access Admin Dashboard

**Problem**: "Please login to access the admin panel"

**Solutions**:
1. Make sure you're using an admin account (check pin.json)
2. Login at `/admin/login.php` first
3. Verify your PIN is correct
4. Check if your role is set to "admin" in pin.json

### Email Not Sending

**Problem**: PIN recovery email not received

**Solutions**:
1. Verify PHPMailer is installed: `composer require phpmailer/phpmailer`
2. Check SMTP credentials in email_config.php
3. For Gmail, use App Password (not regular password)
4. Check spam/junk folder
5. Verify email exists in pin.json
6. Check PHP error logs

### "Access Denied" Error

**Problem**: "Admin privileges required"

**Solutions**:
1. Check your role in pin.json
2. Make sure role is set to "admin" (not "user")
3. Logout and login again
4. Clear browser cache and cookies

### PIN Copy Not Working

**Problem**: Click-to-copy doesn't work

**Solutions**:
1. Use a modern browser (Chrome, Firefox, Edge)
2. Enable clipboard permissions
3. Use HTTPS if on production
4. Check browser console for errors

## 📝 File Structure

```
searchgatecode/
├── composer.json                    # Dependencies
├── composer.lock                    # Locked versions
├── vendor/                         # PHPMailer
├── INSTALL_PHPMAILER.md            # Email setup guide
├── AUTHENTICATION_GUIDE.md         # This file
└── public/
    ├── admin/
    │   ├── login.php               # Admin login page
    │   ├── forgot-pin.php          # PIN recovery
    │   ├── logout.php              # Logout handler
    │   └── includes/
    │       ├── config.php          # Auth functions
    │       └── email_config.php    # SMTP config
    └── data/
        ├── pin.json                # Users with roles
        └── settings.json           # System settings
```

## 🎯 Best Practices

1. **Always use session-based login** (don't rely on ?key=)
2. **Set strong SMTP passwords** in email_config.php
3. **Use App Passwords for Gmail** (never regular password)
4. **Review user roles regularly** in Users page
5. **Keep email addresses updated** for recovery
6. **Test email recovery** before users need it
7. **Backup pin.json** before making changes
8. **Use unique PINs** for each user
9. **Logout when done** to end session securely
10. **Monitor user activity** in User History page

## 📞 Support

- **Documentation**: See UPDATE.md for full features
- **Email Setup**: See INSTALL_PHPMAILER.md
- **PHPMailer Docs**: https://github.com/PHPMailer/PHPMailer
- **Issues**: Check PHP error logs and browser console

---

**Version**: 1.1.6
**Last Updated**: January 2025
**Feature**: Role-Based Authentication + Email Recovery
