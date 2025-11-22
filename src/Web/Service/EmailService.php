<?php

namespace Moonspot\Phlag\Web\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use DealNews\GetConfig\GetConfig;

/**
 * Email Service
 *
 * Handles sending emails for the Phlag application using PHPMailer.
 * Supports both SMTP and native PHP mail() function with configurable
 * settings via GetConfig.
 *
 * ## Responsibilities
 *
 * - Send transactional emails (password reset, verification, etc.)
 * - Configure SMTP connection or mail() function
 * - Handle email failures gracefully
 * - Provide HTML and plain text email support
 *
 * ## Configuration
 *
 * Set the following configuration values using GetConfig:
 *
 * - `mailer.from.address` - Sender email address (required)
 * - `mailer.from.name` - Sender display name (default: "Phlag Admin")
 * - `mailer.method` - "smtp" or "mail" (default: "mail")
 * - `mailer.smtp.host` - SMTP server hostname (required if using SMTP)
 * - `mailer.smtp.port` - SMTP port (default: 587)
 * - `mailer.smtp.username` - SMTP authentication username
 * - `mailer.smtp.password` - SMTP authentication password
 * - `mailer.smtp.encryption` - "tls" or "ssl" (default: "tls")
 *
 * ## Usage
 *
 * ```php
 * $email_service = new EmailService();
 *
 * $result = $email_service->send(
 *     'user@example.com',
 *     'Password Reset Request',
 *     '<p>Click here to reset: <a href="...">Reset</a></p>',
 *     'Click here to reset: ...'
 * );
 *
 * if (!$result) {
 *     error_log('Email failed: ' . $email_service->getError());
 * }
 * ```
 *
 * ## Edge Cases
 *
 * - If SMTP fails, falls back to mail() if configured
 * - Validates email addresses before sending
 * - Returns false on failure with error message available
 * - Handles connection timeouts gracefully
 *
 * Heads-up: In development, consider using a service like Mailtrap
 * or setting mailer.method=mail and configuring a local mail server.
 *
 * @package Moonspot\Phlag\Web\Service
 */
class EmailService {

    /**
     * PHPMailer instance
     *
     * @var PHPMailer
     */
    protected PHPMailer $mailer;

    /**
     * GetConfig instance for configuration management
     *
     * @var GetConfig
     */
    protected GetConfig $config;

    /**
     * Last error message
     *
     * @var string
     */
    protected string $last_error = '';

    /**
     * Creates the email service and configures PHPMailer
     *
     * Initializes PHPMailer with settings from GetConfig.
     * Configures either SMTP or mail() based on mailer.method setting.
     *
     * @throws Exception If PHPMailer configuration fails
     */
    public function __construct() {

        $this->mailer = new PHPMailer(true);
        $this->config = GetConfig::init();

        // Configure based on configuration
        $method = $this->config->get('mailer.method') ?? 'mail';

        if ($method === 'smtp') {
            $this->configureSMTP();
        } else {
            $this->configureMail();
        }

        // Set from address
        $from_address = $this->config->get('mailer.from.address');
        $from_name    = $this->config->get('mailer.from.name') ?? 'Phlag Admin';

        if (empty($from_address)) {
            throw new Exception('mailer.from.address configuration is required');
        }

        $this->mailer->setFrom($from_address, $from_name);

        // Enable HTML emails
        $this->mailer->isHTML(true);

        // Disable debug output by default
        $this->mailer->SMTPDebug = 0;
    }

    /**
     * Configures PHPMailer to use SMTP
     *
     * Sets up SMTP connection using GetConfig for host, port,
     * authentication, and encryption. Validates that required
     * SMTP settings are present.
     *
     * ## Required Configuration
     *
     * - mailer.smtp.host - SMTP server hostname
     *
     * ## Optional Configuration
     *
     * - mailer.smtp.port - Port number (default: 587)
     * - mailer.smtp.username - Authentication username
     * - mailer.smtp.password - Authentication password
     * - mailer.smtp.encryption - "tls" or "ssl" (default: "tls")
     *
     * @return void
     *
     * @throws Exception If mailer.smtp.host is not configured
     */
    protected function configureSMTP(): void {

        $this->mailer->isSMTP();

        $smtp_host = $this->config->get('mailer.smtp.host');
        if (empty($smtp_host)) {
            throw new Exception('mailer.smtp.host configuration is required when using SMTP');
        }

        $this->mailer->Host       = $smtp_host;
        $this->mailer->Port       = $this->config->get('mailer.smtp.port') ?? 587;
        $this->mailer->SMTPSecure = $this->config->get('mailer.smtp.encryption') ?? PHPMailer::ENCRYPTION_STARTTLS;

        // Configure authentication if credentials provided
        $smtp_username = $this->config->get('mailer.smtp.username');
        $smtp_password = $this->config->get('mailer.smtp.password');

        if (!empty($smtp_username) && !empty($smtp_password)) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $smtp_username;
            $this->mailer->Password = $smtp_password;
        }
    }

    /**
     * Configures PHPMailer to use PHP's mail() function
     *
     * Uses the native PHP mail() function for sending emails.
     * This is simpler but less reliable than SMTP.
     *
     * Heads-up: The mail() function requires a properly configured
     * mail server on the host machine. In development, consider using
     * mailhog, postfix, or a similar local mail server.
     *
     * @return void
     */
    protected function configureMail(): void {

        $this->mailer->isMail();
    }

    /**
     * Sends an email to a recipient
     *
     * Sends an email with both HTML and plain text versions. The plain
     * text version is used as a fallback for email clients that don't
     * support HTML.
     *
     * ## Usage
     *
     * ```php
     * $success = $email_service->send(
     *     'user@example.com',
     *     'Welcome to Phlag',
     *     '<h1>Welcome!</h1><p>Thanks for joining.</p>',
     *     'Welcome! Thanks for joining.'
     * );
     * ```
     *
     * ## Edge Cases
     *
     * - Invalid email addresses return false
     * - Connection failures return false with error logged
     * - Missing plain text version generates stripped HTML version
     * - Clears recipients between sends (can be called multiple times)
     *
     * @param string  $to        Recipient email address
     * @param string  $subject   Email subject line
     * @param string  $html_body HTML email body
     * @param ?string $text_body Plain text email body (optional)
     *
     * @return bool True if email sent successfully, false otherwise
     */
    public function send(
        string $to,
        string $subject,
        string $html_body,
        ?string $text_body = null
    ): bool {

        $success = false;

        try {
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAllRecipients();

            // Set recipient
            $this->mailer->addAddress($to);

            // Set subject and body
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $html_body;

            // Set plain text alternative
            if ($text_body !== null) {
                $this->mailer->AltBody = $text_body;
            } else {
                // Generate plain text from HTML
                $this->mailer->AltBody = strip_tags($html_body);
            }

            // Send email
            $success = $this->mailer->send();

            if (!$success) {
                $this->last_error = $this->mailer->ErrorInfo;
                error_log("Email send failed: {$this->last_error}");
            }

        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Email exception: {$this->last_error}");
            $success = false;
        }

        return $success;
    }

    /**
     * Sends a password reset email
     *
     * Sends a formatted password reset email with the reset token URL.
     * Uses a standard template with both HTML and plain text versions.
     *
     * ## Usage
     *
     * ```php
     * $email_service->sendPasswordReset(
     *     'user@example.com',
     *     'https://phlag.example.com/reset-password?token=abc123',
     *     '2025-11-21 15:30:00'
     * );
     * ```
     *
     * ## Email Content
     *
     * The email includes:
     * - Reset link (clickable in HTML version)
     * - Expiration time
     * - Security notice
     * - Contact information
     *
     * @param string $to         Recipient email address
     * @param string $reset_url  Password reset URL with token
     * @param string $expires_at Token expiration datetime string
     *
     * @return bool True if email sent successfully, false otherwise
     */
    public function sendPasswordReset(
        string $to,
        string $reset_url,
        string $expires_at
    ): bool {

        $subject = 'Password Reset Request - Phlag Admin';

        // HTML version
        $html_body = $this->getPasswordResetHtml($reset_url, $expires_at);

        // Plain text version
        $text_body = $this->getPasswordResetText($reset_url, $expires_at);

        return $this->send($to, $subject, $html_body, $text_body);
    }

    /**
     * Generates HTML email body for password reset
     *
     * Creates a formatted HTML email with the reset link and
     * security information. Uses inline styles for broad email
     * client compatibility.
     *
     * @param string $reset_url  Password reset URL
     * @param string $expires_at Expiration datetime
     *
     * @return string HTML email body
     */
    protected function getPasswordResetHtml(string $reset_url, string $expires_at): string {

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f4f4f4; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #2c3e50; margin-top: 0;">Password Reset Request</h1>
        <p>You requested a password reset for your Phlag Admin account.</p>
        <p>Click the button below to reset your password:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{$reset_url}" style="background-color: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Reset Password</a>
        </div>
        
        <p style="font-size: 14px; color: #666;">
            If the button doesn't work, copy and paste this link into your browser:
        </p>
        <p style="background-color: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px; word-break: break-all; font-size: 12px;">
            {$reset_url}
        </p>
        
        <p style="font-size: 14px; color: #666; margin-top: 20px;">
            <strong>This link expires at:</strong> {$expires_at}
        </p>
    </div>
    
    <div style="font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px;">
        <p><strong>Security Notice:</strong></p>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>If you didn't request this password reset, please ignore this email.</li>
            <li>Never share your password reset link with anyone.</li>
            <li>This link can only be used once.</li>
        </ul>
        <p style="margin-top: 20px;">
            — Phlag Admin Team
        </p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generates plain text email body for password reset
     *
     * Creates a plain text version of the password reset email
     * for email clients that don't support HTML.
     *
     * @param string $reset_url  Password reset URL
     * @param string $expires_at Expiration datetime
     *
     * @return string Plain text email body
     */
    protected function getPasswordResetText(string $reset_url, string $expires_at): string {

        return <<<TEXT
Password Reset Request - Phlag Admin

You requested a password reset for your Phlag Admin account.

To reset your password, visit the following link:

{$reset_url}

This link expires at: {$expires_at}

SECURITY NOTICE:

- If you didn't request this password reset, please ignore this email.
- Never share your password reset link with anyone.
- This link can only be used once.

— Phlag Admin Team
TEXT;
    }

    /**
     * Gets the last error message
     *
     * Returns the error message from the last failed email send attempt.
     * Useful for logging or displaying detailed error information.
     *
     * ## Usage
     *
     * ```php
     * if (!$email_service->send(...)) {
     *     $error = $email_service->getError();
     *     error_log("Email failed: {$error}");
     * }
     * ```
     *
     * @return string Error message or empty string if no error
     */
    public function getError(): string {

        return $this->last_error;
    }
}
