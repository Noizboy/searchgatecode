# PHPMailer Installation Guide

This guide explains how to install and configure PHPMailer for the PIN recovery email system.

## Prerequisites

- PHP 7.4 or higher
- Composer (PHP dependency manager)
- SMTP email server credentials (Gmail, Outlook, or custom SMTP)

## Step 1: Install Composer (if not already installed)

### Windows (XAMPP):
1. Download Composer from: https://getcomposer.org/download/
2. Run the installer and follow the instructions
3. Restart your terminal/command prompt

### Verify Installation:
```bash
composer --version
```

## Step 2: Install PHPMailer

Navigate to your project directory and run:

```bash
cd c:\xampp\htdocs\searchgatecode
composer require phpmailer/phpmailer
```

This will:
- Download PHPMailer and dependencies
- Create a `vendor/` folder
- Generate `composer.lock` file
- Update `composer.json`

## Step 3: Configure SMTP Settings

Edit the file: `public/admin/includes/email_config.php`

### For Gmail:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password'); // Use App Password, not regular password
```

**Important for Gmail:**
- You must use an "App Password" instead of your regular Gmail password
- Enable 2-Factor Authentication on your Google account
- Generate an App Password: https://myaccount.google.com/apppasswords
- Use the 16-character app password in the config

### For Outlook/Office 365:

```php
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-email@outlook.com');
define('SMTP_PASSWORD', 'your-password');
```

### For Custom SMTP:

```php
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 587); // or 465 for SSL
define('SMTP_SECURE', 'tls'); // or 'ssl'
define('SMTP_USERNAME', 'smtp-username');
define('SMTP_PASSWORD', 'smtp-password');
```

## Step 4: Update Settings Email

1. Login to admin dashboard
2. Go to Settings page (admin role required)
3. Enter the Administrator Email (this will be used as FROM address)
4. Save settings

## Step 5: Test Email Recovery

1. Go to: `http://localhost/searchgatecode/public/admin/forgot-pin.php`
2. Enter a registered email address
3. Check your inbox (and spam folder)
4. You should receive the PIN recovery email

## Troubleshooting

### Error: "PHPMailer not installed"
- Run: `composer require phpmailer/phpmailer`
- Verify `vendor/autoload.php` exists

### Error: "SMTP connect() failed"
- Check SMTP credentials are correct
- Verify SMTP_HOST and SMTP_PORT
- Check firewall/antivirus settings
- Try using SSL (port 465) instead of TLS (port 587)

### Gmail Error: "Invalid credentials"
- Use App Password, not regular password
- Enable 2-Factor Authentication first
- Generate new App Password

### Email not received:
- Check spam/junk folder
- Verify email exists in pin.json
- Check PHP error logs for details
- Test SMTP connection separately

## File Structure

After installation:
```
searchgatecode/
├── composer.json           # Package configuration
├── composer.lock          # Locked dependency versions
├── vendor/                # PHPMailer and dependencies
│   ├── phpmailer/
│   └── autoload.php
└── public/
    └── admin/
        ├── includes/
        │   └── email_config.php    # SMTP configuration
        ├── login.php               # Admin login
        ├── forgot-pin.php          # PIN recovery
        └── logout.php              # Logout
```

## Security Notes

- **Never commit SMTP credentials to version control**
- Add `vendor/` to `.gitignore`
- Use environment variables for production
- Use App Passwords for Gmail (never use main password)
- Keep PHPMailer updated: `composer update`

## Alternative: Sendmail (Local Development)

If you don't have SMTP credentials, you can use PHP's built-in mail() function (not recommended for production):

Edit `forgot-pin.php` and replace the send_email() call with:
```php
$headers = 'From: noreply@localhost' . "\r\n" .
           'Content-Type: text/html; charset=UTF-8';
mail($to, $subject, $body, $headers);
```

## Support

If you encounter issues:
1. Check PHP error logs
2. Verify SMTP settings
3. Test with a simple PHPMailer script
4. Consult PHPMailer docs: https://github.com/PHPMailer/PHPMailer
