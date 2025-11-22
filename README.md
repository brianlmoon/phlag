# Phlag

**Feature flag management system with RESTful API and web admin interface**

Phlag lets you control feature rollouts and configuration values across your applications with temporal scheduling and 
type-safe values. Built with PHP 8.4+, it provides both a web UI for management and APIs for flag consumption.

## Features

- 🎯 **Typed Flags**: SWITCH (boolean), INTEGER, FLOAT, STRING
- ⏰ **Temporal Control**: Schedule flags with start/end dates
- 🌐 **Web Interface**: Clean admin UI for managing flags, API keys, and users
- 🔑 **Auto-generated API Keys**: 64-character cryptographically secure keys
- 📧 **Password Reset**: Email-based password recovery
- 🗄️ **Multi-Database**: MySQL, PostgreSQL, SQLite support

## Quick Start

### Requirements

- PHP 8.4 or higher
- Composer
- One of: MySQL 5.7+, PostgreSQL 9.6+, or SQLite 3
- Web server (Apache, Nginx, or PHP built-in server)
- (Optional) SMTP server for password reset emails

### Installation

1. **Clone the repository**

```bash
git clone https://github.com/brianlmoon/phlag.git
cd phlag
```

2. **Install dependencies**

```bash
composer install
```

3. **Set up the database**

Choose your database and run the appropriate schema:

```bash
# MySQL
mysql -u root -p your_database < schema/mysql.sql

# PostgreSQL
psql -U postgres -d your_database -f schema/pgsql.sql

# SQLite
sqlite3 phlag.db < schema/sqlite.sql
```

4. **Configure database connection**

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

5. **Configure email (optional, for password reset)**

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

See [QUICKSTART](QUICKSTART.md) for a detailed tutorial of gettings started using the application.

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

### Using the Flag API

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

## Support

For bugs and feature requests, please use the GitHub issue tracker.

For questions and discussion, contact brian@moonspot.net.
