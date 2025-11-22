# Email Setup Guide for Phlag Password Reset

## Quick Start

1. **Copy the example configuration**:
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` and set your email provider details**:
   ```bash
   # Minimum required
   mailer.from.address=noreply@yourdomain.com
   mailer.method=smtp
   mailer.smtp.host=smtp.yourdomain.com
   ```

3. **Test the password reset flow**:
   - Go to `/forgot-password`
   - Enter a username
   - Check the user's email inbox
   - Click the reset link

## Development Setup (Mailtrap)

For development/testing without sending real emails:

1. Sign up at https://mailtrap.io (free)
2. Get your SMTP credentials from their dashboard
3. Add to `.env`:
   ```bash
   mailer.from.address=dev@localhost
   mailer.method=smtp
   mailer.smtp.host=smtp.mailtrap.io
   mailer.smtp.port=2525
   mailer.smtp.encryption=tls
   mailer.smtp.username=your-mailtrap-username
   mailer.smtp.password=your-mailtrap-password
   ```

## Production Setup Examples

### Using Gmail

```bash
mailer.from.address=your-email@gmail.com
mailer.from.name=Phlag Admin
mailer.method=smtp
mailer.smtp.host=smtp.gmail.com
mailer.smtp.port=587
mailer.smtp.encryption=tls
mailer.smtp.username=your-email@gmail.com
mailer.smtp.password=your-app-specific-password
```

**Note**: Generate an App-Specific Password at: https://myaccount.google.com/apppasswords

### Using SendGrid

```bash
mailer.from.address=noreply@yourdomain.com
mailer.method=smtp
mailer.smtp.host=smtp.sendgrid.net
mailer.smtp.port=587
mailer.smtp.encryption=tls
mailer.smtp.username=apikey
mailer.smtp.password=SG.your-sendgrid-api-key
```

### Using Mailgun

```bash
mailer.from.address=noreply@yourdomain.com
mailer.method=smtp
mailer.smtp.host=smtp.mailgun.org
mailer.smtp.port=587
mailer.smtp.encryption=tls
mailer.smtp.username=postmaster@your-domain.mailgun.org
mailer.smtp.password=your-mailgun-smtp-password
```

## How It Works

- **Email configured**: Users receive professional HTML emails with reset links
- **Email not configured**: System falls back to showing tokens on screen (development mode)
- **Graceful degradation**: Application works either way

## Troubleshooting

### No email received?
1. Check spam/junk folder
2. Verify user has email address in database
3. Check error logs: `grep "Email" /path/to/error.log`
4. Verify SMTP credentials

### "Development Mode" showing instead?
- Email service not configured
- Check `.env` file exists and has mailer.from.address
- Check error logs for configuration errors

### SMTP connection fails?
- Verify firewall allows port 587 or 465
- Try telnet: `telnet smtp.yourhost.com 587`
- Check SMTP credentials are correct
- Some ISPs block SMTP - use authenticated relay

## Testing Emails

Send a test email from PHP:
```php
require 'vendor/autoload.php';
$service = new \Moonspot\Phlag\Web\Service\EmailService();
$result = $service->send(
    'test@example.com',
    'Test Email',
    '<h1>It works!</h1>',
    'It works!'
);
echo $result ? 'Sent!' : 'Failed: ' . $service->getError();
```

## Security Notes

- Never commit `.env` file to version control
- Use TLS/SSL encryption for SMTP
- Set up SPF/DKIM records for your domain
- Monitor for email failures in logs

## Documentation

See `EMAIL_INTEGRATION_COMPLETE.md` for full details.
