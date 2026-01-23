# Phlag

**Feature flag management system with RESTful API and web admin interface**

Phlag lets you control feature rollouts and configuration values across your applications with temporal scheduling and 
type-safe values. Built with PHP 8.4+, it provides both a web UI for management and APIs for flag consumption.

## Features

- 🎯 **Typed Flags**: SWITCH (boolean), INTEGER, FLOAT, STRING
- ⏰ **Temporal Control**: Schedule flags with start/end dates
- 🌐 **Web Interface**: Clean admin UI for managing flags, API keys, and users
- 🔑 **Auto-generated API Keys**: 64-character cryptographically secure keys
- 🪝 **Webhooks**: HTTP notifications when flags change with customizable payloads
- 📧 **Password Reset**: Email-based password recovery
- 🔐 **Google OAuth**: Optional Google sign-in for user authentication
- 🗄️ **Multi-Database**: MySQL, PostgreSQL, SQLite support
- 📦 **Client Libraries**: Official JavaScript and PHP clients available

## Quick Start

### Requirements

- PHP 8.4 or higher
- Composer
- One of: MySQL 5.7+, PostgreSQL 9.6+, or SQLite 3
- Web server (Apache, Nginx, or PHP built-in server)
- (Optional) SMTP server for password reset emails

### Installation

1. **Install via Composer**

```bash
composer create-project moonspot/phlag
cd phlag
```

2. **Set up the database**

Choose your database and run the appropriate schema:

```bash
# MySQL
mysql -u root -p your_database < schema/mysql.sql

# PostgreSQL
psql -U postgres -d your_database -f schema/pgsql.sql

# SQLite
sqlite3 phlag.db < schema/sqlite.sql
```

3. **Configure database connection**

Create `etc/config.ini` from the example:

```ini
[db]
db.phlag.type   = mysql
db.phlag.server = localhost
db.phlag.port   = 3306
db.phlag.db     = phlag
db.phlag.user   = phlag_user
db.phlag.pass   = your_secure_password
```

For PostgreSQL, use `type = pgsql`. For SQLite, use `type = sqlite` and set `server` to the path of your .db file.

**Optional: Configure base URL path**

If Phlag is installed in a subdirectory (e.g., `https://example.com/phlag`), add to `etc/config.ini`:

```ini
[phlag]
phlag.base_url_path = /phlag
```

This ensures API responses generate correct resource URLs. Omit this setting if Phlag is at the domain root.

4. **Configure email (optional, for password reset)**

Add to `etc/config.ini`:

```ini
[mailer]
mailer.from.address = noreply@example.com
mailer.method = smtp
mailer.smtp.host = smtp.example.com
mailer.smtp.port = 587
mailer.smtp.encryption = tls
mailer.smtp.username = your-smtp-username
mailer.smtp.password = your-smtp-password
```

See `etc/config.ini.example` for detailed email configuration options including Gmail, SendGrid, and Mailgun examples.

5. **Configure Google OAuth (optional)**

Add to `etc/config.ini`:

```ini
[google_oauth]
google_oauth.enabled = true
google_oauth.client_id = your-client-id.apps.googleusercontent.com
google_oauth.client_secret = your-client-secret
google_oauth.allowed_domains = example.com,company.org
```

**To obtain Google OAuth credentials:**

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing)
3. Navigate to "APIs & Services" → "Credentials"
4. Click "Create Credentials" → "OAuth 2.0 Client ID"
5. Set application type to "Web application"
6. Add authorized redirect URI: `https://your-domain.com/auth/google/callback`
7. Copy the Client ID and Client Secret to your config

**Configuration options:**

| Setting | Required | Description |
|---------|----------|-------------|
| `google_oauth.enabled` | Yes | Set to `true` to enable Google sign-in |
| `google_oauth.client_id` | Yes | OAuth client ID from Google Cloud Console |
| `google_oauth.client_secret` | Yes | OAuth client secret from Google Cloud Console |
| `google_oauth.allowed_domains` | No | Comma-separated list of allowed email domains. Leave empty to allow any Google account |

**User behavior:**
- Users can sign in with either password or Google (both methods work)
- If a Google user's email matches an existing user, accounts are auto-linked
- New Google users are auto-created with their email as username
- The first user must still be created via the password-based `/first-user` flow

6. **Start the application**

For development, use PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

For production, configure your web server to serve `public/` as the document root.

7. **Create your first user**

Navigate to `http://localhost:8000/first-user` and create an admin account. This page only appears when no users exist.

8. **Start managing flags!**

Log in at `http://localhost:8000/login` and you're ready to create feature flags.

### Using Docker

A pre-built Docker image is available at [Docker Hub](https://hub.docker.com/r/brianlmoon/phlag):

```bash
# Pull the image
docker pull brianlmoon/phlag

# Run with MySQL (recommended for production)
docker run -d -p 8000:80 \
  -e DB_PHLAG_TYPE=mysql \
  -e DB_PHLAG_HOST=your-mysql-host \
  -e DB_PHLAG_PORT=3306 \
  -e DB_PHLAG_DB=phlag \
  -e DB_PHLAG_USER=phlag_user \
  -e DB_PHLAG_PASS=your_password \
  brianlmoon/phlag
```

Visit `http://localhost:8000/first-user` to create your initial admin user.

## Web Server Configuration

### Apache

Create a `.htaccess` file in `public/`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### Nginx

```nginx
server {
    listen 80;
    server_name phlag.example.com;
    root /path/to/phlag/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Usage

See [QUICKSTART](QUICKSTART.md) for a detailed tutorial of getting started using the application.

### Client Libraries

Official client libraries are available to simplify integration with Phlag:

#### JavaScript Client

- **Repository**: [phlag-js-client](https://github.com/brianlmoon/phlag-js-client)
- **Use Cases**: Node.js services
- **Features**: Promise-based API, TypeScript support, automatic type casting

```javascript
import PhlagClient from 'phlag-js-client';

const client = new PhlagClient({
  baseUrl: 'http://localhost:8000',
  apiKey: 'your-api-key'
});

const isEnabled = await client.getFlag('feature_checkout');
```

#### PHP Client

- **Repository**: [phlag-php-client](https://github.com/brianlmoon/phlag-php-client)
- **Use Cases**: PHP applications, backend services
- **Features**: Type-safe responses, PSR-compliant, Composer integration

```php
use Phlag\Client\PhlagClient;

$client = new PhlagClient(
    'http://localhost:8000',
    'your-api-key'
);

$isEnabled = $client->getFlag('feature_checkout');
```

For other languages or custom integrations, use the Flag API endpoints directly (see below).

### Managing Flags via Web UI

1. **Create a flag**: Navigate to "Flags" → "Create New Flag"
   - Name: Alphanumeric with underscores/hyphens (e.g., `feature_checkout`)
   - Type: SWITCH, INTEGER, FLOAT, or STRING
   - Value: Type-appropriate value
   - Optional: Set start/end dates for temporal control

2. **Create an API key**: Navigate to "API Keys" → "Create New API Key"
   - Enter description (e.g., "Production Web App")
   - Copy the 64-character key (shown once only!)

3. **Add users**: Navigate to "Users" → "Create New User"
   - Provide username, full name, email, password

### Configuring Webhooks

Webhooks notify external systems when flags change by sending HTTP POST requests with customizable payloads.

#### Creating a Webhook

1. Navigate to "Webhooks" → "Create New Webhook"
2. Configure the webhook:
   - **Name**: Friendly identifier (e.g., "Slack Notifications")
   - **URL**: HTTPS endpoint to receive POST requests
   - **Status**: Active/Inactive toggle
   - **Event Types**: Select which events trigger the webhook:
     - `created` - New flag created
     - `updated` - Existing flag updated
   - **Include environment changes**: Check to fire on environment value changes
   - **Custom Headers**: Optional HTTP headers (e.g., `Authorization: Bearer token`)
   - **Payload Template**: Twig template for JSON payload (default provided)

3. Test the webhook before activating:
   - Click "Test" button
   - Select a flag from the dropdown (uses real flag data and environments)
   - Click "Send Test" to deliver a test payload
   - Verify HTTP status code and response
4. Activate the webhook to start receiving notifications

**Test Behavior:**
- Simulates an `updated` event
- Uses selected flag's current data, including all environments
- Validates Twig template renders correctly
- Sends actual HTTP POST request to configured URL

#### Webhook Payload

The default payload includes:

```json
{
  "event": "updated",
  "flag": {
    "name": "feature_checkout",
    "type": "SWITCH",
    "description": "New checkout flow",
    "environments": [
      {
        "name": "production",
        "value": true,
        "start_datetime": null,
        "end_datetime": null
      }
    ]
  },
  "previous": {
    "name": "feature_checkout",
    "type": "SWITCH",
    "description": "Old checkout flow"
  },
  "timestamp": "2026-01-18T18:00:00+00:00"
}
```

#### Customizing Payloads

Payload templates use Twig syntax with these variables:

- `event_type` - Event name (e.g., "updated")
- `flag` - Current flag object with `name`, `type`, `description`
- `environments` - Array of environment values (separate from flag object)
- `previous` - Previous flag state (on updates only)
- `old_environments` - Previous environment values (on updates only)
- `timestamp` - ISO 8601 timestamp

**Important:** Use the `|raw` filter to prevent HTML escaping in JSON output:

```twig
"value": "{{ env.value|raw }}"
```

Example custom template for Slack:

```twig
{
  "text": "Flag *{{ flag.name|raw }}* was {{ event_type == 'created' ? 'created' : 'updated' }}",
  "blocks": [
    {
      "type": "section",
      "text": {
        "type": "mrkdwn",
        "text": "Type: `{{ flag.type|raw }}`\nDescription: {{ flag.description|raw }}"
      }
    }
  ]
}
```

**Advanced Slack Example with Environments:**

This example demonstrates environment iteration and Slack attachments format. Create an incoming webhook in your Slack workspace settings, then use this template:

```twig
{
  "channel": "#deployments",
  "username": "Phlag Bot",
  "attachments": [
    {
      "fallback": "{{ flag.name|raw }} {{ event_type|raw }}",
      "pretext": "{{ flag.name|raw }} {{ event_type|raw }}",
      "fields": [
        {
          "title": "Flag",
          "value": "{{ flag.name|raw }}",
          "short": true
        }
      ]
    }
    {% for env in environments %},
    {
      "fallback": "{{ env.name|raw }} set to {{ env.value|raw }}",
      "fields": [
        {
          "title": "Environment",
          "value": "{{ env.name|raw }}",
          "short": true
        },
        {
          "title": "Value",
          "value": {{ env.value|json_encode|raw }},
          "short": true
        },
        {
          "title": "Start",
          "value": {{ env.start_datetime|json_encode|raw }},
          "short": true
        },
        {
          "title": "End",
          "value": {{ env.end_datetime|json_encode|raw }},
          "short": true
        }
      ]
    }
    {% endfor %}
  ]
}
```

#### Security Considerations

- **HTTPS Required**: Webhooks must use HTTPS (except localhost for testing)
- **Private IP Blocking**: Webhooks cannot target private IP ranges (10.*, 192.168.*, etc.)
- **Synchronous Delivery**: Webhooks send immediately with 5-second timeout and 1 retry
- **Fail-Safe**: Webhook failures never block flag operations

#### Configuration Options

Global webhook behavior can be configured in `etc/config.ini`:

```ini
[webhooks]
webhooks.timeout = 5         # HTTP request timeout (seconds)
webhooks.max_retries = 1     # Number of retry attempts
```

**Note**: Webhooks are always enabled. If you don't want webhooks to fire, simply don't create any webhook configurations in the admin interface.

### Using the Flag API Directly

Phlag provides three endpoints for retrieving flag values. All require Bearer token authentication.

#### Get Single Flag Value

Returns the current evaluated value as a scalar:

```bash
curl -H "Authorization: Bearer your-api-key" \
     http://localhost:8000/flag/feature_checkout
```

Response examples:
```json
true                    # SWITCH flag (active)
false                   # SWITCH flag (inactive)
100                     # INTEGER flag
3.14                    # FLOAT flag
"welcome message"       # STRING flag
null                    # Inactive or non-existent flag
```

#### Get All Flag Values

Returns all flags as a key-value object:

```bash
curl -H "Authorization: Bearer your-api-key" \
     http://localhost:8000/all-flags
```

Response:
```json
{
  "feature_checkout": true,
  "max_items": 100,
  "price_multiplier": 1.5,
  "welcome_message": "Hello World"
}
```

#### Get All Flags with Metadata

Returns complete flag details including temporal constraints:

```bash
curl -H "Authorization: Bearer your-api-key" \
     http://localhost:8000/get-flags
```

Response:
```json
[
  {
    "name": "feature_checkout",
    "type": "SWITCH",
    "value": true,
    "start_datetime": null,
    "end_datetime": null
  },
  {
    "name": "holiday_promo",
    "type": "SWITCH",
    "value": true,
    "start_datetime": "2025-12-01T00:00:00+00:00",
    "end_datetime": "2025-12-31T23:59:59+00:00"
  }
]
```

### Temporal Scheduling

Flags can be scheduled to activate/deactivate automatically:

- **Start datetime**: Flag becomes active at this time
- **End datetime**: Flag becomes inactive after this time
- Both are optional (null = no constraint)

**Behavior when inactive:**
- SWITCH flags return `false`
- INTEGER/FLOAT/STRING flags return `null`

## Application Architecture

### Directory Structure

```
phlag/
├── etc/
│   └── config.ini          # Database and email configuration
├── public/
│   ├── index.php           # Application entry point
│   └── assets/             # CSS, JavaScript, images
├── schema/
│   ├── mysql.sql           # MySQL schema
│   ├── pgsql.sql           # PostgreSQL schema
│   └── sqlite.sql          # SQLite schema
├── src/
│   ├── Action/             # Custom API endpoints
│   ├── Data/               # Value objects (Phlag, PhlagApiKey, PhlagUser)
│   ├── Mapper/             # Data mappers with auto-features
│   └── Web/                # Controllers, templates, security
├── tests/                  # PHPUnit tests
└── vendor/                 # Composer dependencies
```

### Security Features

- **CSRF Protection**: Token-based protection on login and user creation forms
- **Password Security**: Bcrypt hashing with cost factor 12
- **API Key Generation**: Cryptographically secure random_bytes()
- **Session Security**: ID regeneration, timeout tracking, destruction on logout
- **Google OAuth**: Secure OAuth 2.0 flow with state parameter CSRF protection
- **XSS Prevention**: Twig auto-escaping, manual escaping in JavaScript
- **Input Validation**: Type checking, pattern matching, length constraints

## Development

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run with detailed output
./vendor/bin/phpunit --testdox

# Run specific test file
./vendor/bin/phpunit tests/Unit/Action/GetPhlagStateTest.php

# Run specific test
./vendor/bin/phpunit --filter testGetActiveSwitchFlagReturnsTrue
```

### Database Migrations

Schema changes are tracked in the `schema/` directory. To update your database:

#### Upgrading to v2.0 (Webhooks Feature)

If you're upgrading from a version before webhooks were added, run this migration:

**MySQL:**
```sql
CREATE TABLE IF NOT EXISTS phlag_webhooks (
    phlag_webhook_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    headers_json TEXT,
    payload_template TEXT,
    event_types_json TEXT NOT NULL,
    include_environment_changes TINYINT(1) NOT NULL DEFAULT 0,
    create_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (phlag_webhook_id),
    KEY name (name),
    KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

**PostgreSQL:**
```sql
CREATE TABLE IF NOT EXISTS phlag_webhooks (
    phlag_webhook_id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    headers_json TEXT,
    payload_template TEXT,
    event_types_json TEXT NOT NULL,
    include_environment_changes BOOLEAN NOT NULL DEFAULT false,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webhook_name ON phlag_webhooks(name);
CREATE INDEX IF NOT EXISTS idx_webhook_active ON phlag_webhooks(is_active);
```

**SQLite:**
```sql
CREATE TABLE IF NOT EXISTS phlag_webhooks (
    phlag_webhook_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    headers_json TEXT,
    payload_template TEXT,
    event_types_json TEXT NOT NULL,
    include_environment_changes INTEGER NOT NULL DEFAULT 0,
    create_datetime TEXT NOT NULL DEFAULT (datetime('now')),
    update_datetime TEXT
);

CREATE INDEX IF NOT EXISTS idx_webhook_name ON phlag_webhooks(name);
CREATE INDEX IF NOT EXISTS idx_webhook_active ON phlag_webhooks(is_active);
```

After running the migration, webhooks will automatically fire when flags change. Configure your first webhook via the admin UI at `/webhooks`.

### Adding New Features

1. Write unit tests first (TDD approach)
2. Implement data models and mappers
3. Update schema files
4. Create actions/controllers
5. Add templates and JavaScript
6. Run tests to verify

## Troubleshooting

### Flag Returns Wrong Type

Check the flag's type and temporal constraints:
- SWITCH flags return `false` when inactive (not `null`)
- Other types return `null` when inactive
- Verify start/end datetimes are correct

### Email Not Sending

Verify SMTP configuration in `etc/config.ini`:
```bash
# Test SMTP connection
php -r "
  require 'vendor/autoload.php';
  \$smtp = new PHPMailer\PHPMailer\SMTP();
  \$smtp->setDebugLevel(2);
  \$smtp->connect('smtp.example.com', 587);
"
```

### Database Connection Failed

Verify credentials in `etc/config.ini` and ensure database server is running:
```bash
# MySQL
mysql -u phlag_user -p -h localhost phlag

# PostgreSQL
psql -U phlag_user -h localhost -d phlag
```

## Contributing

Contributions are welcome! Phlag follows strict coding standards:

- PSR-1 and PSR-12 compliance
- 1TBS brace style
- snake_case for variables/properties
- camelCase for methods
- Type declarations on all methods
- Protected visibility (not private) unless truly encapsulated
- PHPDoc in Knowledge Base conversational style

See `AGENTS.md` for complete coding standards and architecture details.

## License

BSD 3-Clause License

Copyright (c) 2025, Brian Moon

See [LICENSE](LICENSE) file for full text.

## Credits

Built by Brian Moon (brian@moonspot.net)

**Key Dependencies:**
- [PageMill Router](https://github.com/dealnews/pagemill-router) - Routing
- [DealNews DataMapper](https://github.com/dealnews/data-mapper) - ORM
- [Twig](https://twig.symfony.com/) - Templating
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) - Email
- [league/oauth2-google](https://github.com/thephpleague/oauth2-google) - Google OAuth

## Support

For bugs and feature requests, please use the GitHub issue tracker.

For questions and discussion, contact brian@moonspot.net.
