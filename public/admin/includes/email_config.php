<?php
/**
 * Email Configuration
 * Configure your SMTP settings here
 */

// Load settings from settings.json
$settings_file = __DIR__ . '/../../data/settings.json';
$admin_email = '';

if (file_exists($settings_file)) {
    $settings_data = json_decode(file_get_contents($settings_file), true);
    $admin_email = $settings_data['admin_email'] ?? '';
}

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP host
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_USERNAME', $admin_email); // Your email
define('SMTP_PASSWORD', ''); // Your email password or app password
define('SMTP_FROM_EMAIL', $admin_email);
define('SMTP_FROM_NAME', 'Gate Codes Admin');

/**
 * Send email using PHPMailer
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body
 * @param string $altBody Plain text body (optional)
 * @return bool Success status
 */
function send_email($to, $subject, $body, $altBody = '') {
    // Check if PHPMailer is available
    $phpmailer_path = __DIR__ . '/../../../vendor/autoload.php';

    if (!file_exists($phpmailer_path)) {
        error_log('PHPMailer not installed. Please run: composer require phpmailer/phpmailer');
        return false;
    }

    require_once $phpmailer_path;

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Get PIN recovery email template
 *
 * @param string $name User name
 * @param string $pin User PIN
 * @return string HTML email template
 */
function get_pin_recovery_template($name, $pin) {
    $template = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIN Recovery</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3bdd82, #2bc76a); padding: 40px 40px 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                🔐 PIN Recovery
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Hi <strong>' . htmlspecialchars($name) . '</strong>,
                            </p>

                            <p style="margin: 0 0 30px 0; color: #666666; font-size: 15px; line-height: 1.6;">
                                You requested to recover your PIN for the Gate Codes application. Here are your access credentials:
                            </p>

                            <!-- PIN Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 30px 0;">
                                <tr>
                                    <td style="background: linear-gradient(135deg, #2c3e50, #34495e); padding: 30px; border-radius: 10px; text-align: center;">
                                        <p style="margin: 0 0 10px 0; color: #ecf0f1; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                            Your PIN
                                        </p>
                                        <p style="margin: 0; color: #ffffff; font-size: 36px; font-weight: 700; font-family: \'Courier New\', monospace; letter-spacing: 8px;">
                                            ' . htmlspecialchars($pin) . '
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; margin: 0 0 30px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.5;">
                                    <strong>⚠️ Security Notice:</strong> Keep this PIN secure and do not share it with anyone. If you did not request this PIN recovery, please contact your administrator immediately.
                                </p>
                            </div>

                            <p style="margin: 0 0 20px 0; color: #666666; font-size: 15px; line-height: 1.6;">
                                Use this PIN to access the Gate Codes application and admin dashboard.
                            </p>

                            <p style="margin: 0; color: #999999; font-size: 13px; line-height: 1.6;">
                                If you have any questions or need assistance, please reply to this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px 40px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
                                <strong>Gate Codes Admin System</strong>
                            </p>
                            <p style="margin: 0; color: #999999; font-size: 12px;">
                                This is an automated message. Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Email Footer -->
                <table width="600" cellpadding="0" cellspacing="0" style="margin-top: 20px;">
                    <tr>
                        <td style="text-align: center; padding: 20px;">
                            <p style="margin: 0; color: #999999; font-size: 12px;">
                                © ' . date('Y') . ' Gate Codes. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

    return $template;
}
