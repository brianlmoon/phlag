# Phlag - AI Agent Quick Reference

## What It Is
Feature flag management system (PHP 8.0+) with RESTful API and web admin UI. Manages typed flags (SWITCH, INTEGER, FLOAT, STRING, JSON) with temporal scheduling.

## Stack
- **Backend**: PHP 8.0+, Twig 3.x, PageMill Router, DealNews DataMapper
- **Frontend**: Vanilla JS (XMLHttpRequest), CSS3
- **Database**: MySQL (primary), PostgreSQL, SQLite

## Key Architecture

### Data Layer
- **Value Objects**: `Phlag`, `PhlagApiKey`, `PhlagUser`, `GoogleUser`, `PasswordResetToken`, `PhlagSession`, `PhlagWebhook`, `PhlagEnvironment`, `PhlagEnvironmentValue`, `PhlagApiKeyEnvironment` (in `src/Data/`)
- **Mappers**: Auto-generate API keys (64-char), hash passwords (bcrypt), validate webhooks
- **Repository**: Singleton with `init()`, auto-registers mappers

### Action Layer (Custom Endpoints)
- **FlagValueTrait**: Shared temporal logic + type casting (including JSON parsing)
- **ApiKeyAuthTrait**: Bearer token validation for flag API endpoints
- **GetPhlagState** (`/flag/{name}`): Single flag value (typed scalar or JSON object/array)
- **GetAllFlags** (`/all-flags`): All flags as key-value object (JSON parsed)
- **GetFlags** (`/get-flags`): All flags with metadata (ISO 8601 dates)
- **PhlagWebhook/Test** (`/webhook/test/{id}`): Test webhook delivery

### Web Layer
- **Controllers**: BaseController (auth/CSRF), Auth, Phlag, ApiKey, User, Environment, PhlagWebhook, Home
- **Services**: WebhookDispatcher (HTTP POST with retry), EmailService (password reset), GoogleOAuthService (SSO)
- **Security**: CsrfToken (256-bit tokens), SessionManager (timeout handling), DatabaseSessionHandler (multi-instance)
- **Templates**: Twig in `src/Web/templates/`
- **JavaScript**: `app.js` (ApiClient, utils), `phlag.js`, `api_key.js`, `user.js`

## Authentication Model

### Session-Based (Web UI + Admin API)
- **Endpoints**: `/api/Phlag/*`, `/api/PhlagApiKey/*`, `/api/PhlagUser/*`
- **Method**: Session cookies (auto-sent by browser)
- **Protected**: All CRUD operations
- **CSRF**: Protected login/first-user forms

### Bearer Token (Flag API)
- **Endpoints**: `/flag/{name}`, `/all-flags`, `/get-flags`
- **Method**: `Authorization: Bearer <api_key>` header
- **Protected**: Flag state retrieval only
- **Trait**: `ApiKeyAuthTrait` handles validation

### Google OAuth (Optional)
- **Endpoints**: `/auth/google`, `/auth/google/callback`
- **Service**: `GoogleOAuthService` wraps league/oauth2-google
- **Config**: `etc/config.ini` with `google_oauth.*` settings
- **Behavior**: Auto-creates users, auto-links by email match
- **Domain restriction**: Optional comma-separated allowed domains

### First-Time Setup
1. Empty `phlag_users` table triggers redirect to `/first-user`
2. Create initial user (username, full_name, password)
3. Auto-login and redirect to dashboard

## Critical API Details

### Admin API (Session Auth Required)
```
GET/POST/PUT/DELETE /api/Phlag/                # CRUD for flags
GET/POST/PUT/DELETE /api/PhlagApiKey/          # CRUD for API keys
GET/POST/PUT/DELETE /api/PhlagUser/            # CRUD for users
GET/POST/PUT/DELETE /api/PhlagEnvironment/     # CRUD for environments
GET/POST/PUT/DELETE /api/PhlagWebhook/         # CRUD for webhooks
POST                /webhook/test/{id}          # Test webhook delivery
```
**Important**: Trailing slashes required, returns 401 if not logged in

### Flag State API (Bearer Auth Required)
```
GET /flag/{name}      # Returns: true, 100, 1.5, "text", {obj}, [arr], false, or null
GET /all-flags        # Returns: {"flag1": true, "flag2": {"key": "val"}, ...}
GET /get-flags        # Returns: [{name, type, value, start, end}, ...]
```
**Trailing slash optional**

### Temporal Logic
Flag is **active** when:
- `start_datetime` is NULL or ≤ now
- `end_datetime` is NULL or ≥ now

**Inactive behavior**:
- SWITCH → `false`
- INTEGER/FLOAT/STRING/JSON → `null`

### Type Casting
- SWITCH → boolean
- INTEGER → int
- FLOAT → float
- STRING → string (no casting)
- JSON → object or array (parsed from JSON string)

## Database Schema

### Tables (all prefixed `phlag_`)
- **phlags**: `phlag_id`, name, type (ENUM: SWITCH/INTEGER/FLOAT/STRING/JSON), description, create_datetime, update_datetime
- **phlag_environments**: `phlag_environment_id`, name, description, create_datetime, update_datetime
- **phlag_environment_values**: `phlag_environment_value_id`, phlag_id, phlag_environment_id, value (MEDIUMTEXT), start_datetime, end_datetime
- **phlag_api_keys**: `plag_api_key_id`, description, api_key (64 chars), create_datetime
- **phlag_api_key_environments**: `phlag_api_key_environment_id`, plag_api_key_id, phlag_environment_id
- **phlag_users**: `phlag_user_id`, username, full_name, email, password (bcrypt), google_id, create_datetime, update_datetime
- **phlag_sessions**: `session_id`, session_data, last_activity, create_datetime
- **phlag_password_reset_tokens**: `password_reset_token_id`, phlag_user_id, token (64 chars), expiration_datetime, create_datetime
- **phlag_webhooks**: `phlag_webhook_id`, name, url, is_active, headers_json, payload_template, event_types_json, include_environment_changes, create_datetime, update_datetime

### Naming Convention
- Primary keys: `{table_name}_id`
- Sort order: Alphabetical by name (A-Z)
- Foreign keys: Cascade on delete where appropriate

## Security Features

✅ **Passwords**: Bcrypt hashing, 8-char minimum, confirmation on create  
✅ **API Keys**: Crypto-secure 64-char generation, one-time display, masking  
✅ **CSRF**: 256-bit tokens, timing-safe comparison, replay protection  
✅ **Sessions**: Regeneration on login, destruction on logout, 30-min timeout  
✅ **Session Storage**: Database-backed sessions for multi-instance support  
✅ **XSS**: HTML escaping in templates and JS  
✅ **Google OAuth**: Optional SSO with domain restrictions  
✅ **Webhook Validation**: URL format, HTTPS requirement (except localhost), private IP blocking  

## Exception Handling

### Common Exceptions

**InvalidArgumentException** (validation errors):
- `PhlagEnvironmentValue`: "Invalid JSON format" / "JSON must be an object or array"
- `PhlagWebhook`: "URL is required" / "URL is not valid" / "URL must use HTTPS" / "URL points to private IP address" / "Event types are required" / "Event types must be JSON array" / "Headers must be JSON object"

**RuntimeException** (operational errors):
- `WebhookDispatcher`: "curl_init failed" / "Webhook request failed: {error}"
- `PhlagSession`: "Session data exceeds maximum size"

**Best Practices**:
- Always catch `\Throwable` not `\Exception`
- Log exceptions but don't expose internals to users
- Webhook failures should never block save operations (fail-safe design)  

## Testing

**Suite**: 221 tests, 100% pass rate

```bash
./vendor/bin/phpunit                    # Run all tests
./vendor/bin/phpunit --testdox          # Detailed output
./vendor/bin/phpunit --filter testName  # Specific test
```

**Coverage**:
- `FlagValueTraitTest.php` (29 tests) - Temporal + type casting + JSON parsing
- `GetPhlagStateTest.php` (15 tests) - Single flag endpoint
- `GetAllFlagsTest.php` (10 tests) - Bulk flag endpoint
- `GetFlagsTest.php` (14 tests) - Detailed flag endpoint
- `ApiKeyAuthTraitTest.php` (20 tests) - Bearer token authentication
- `CsrfTokenTest.php` (17 tests) - CSRF protection
- `SessionManagerTest.php` (21 tests) - Session timeout and lifecycle
- `DatabaseSessionHandlerTest.php` (16 tests) - Database session storage
- `GoogleOAuthServiceTest.php` (24 tests) - Google OAuth service
- `EmailServiceTest.php` (19 tests) - Email sending and token generation
- `PasswordResetTokenTest.php` (11 tests) - Token generation and expiration
- `PhlagWebhookMapperTest.php` (12 tests) - Webhook URL and JSON validation
- `WebhookDispatcherTest.php` (13 tests) - Webhook HTTP delivery and retry logic

**Testing with Mocks**:
Tests avoid database dependencies by using PHPUnit mocks:
```php
// Create mocked repository
$repository = $this->createMock(Repository::class);

// Configure expectations
$repository->expects($this->once())
    ->method('save')
    ->with('PhlagSession', $this->isInstanceOf(PhlagSession::class))
    ->willReturn($saved_session);

// Inject via constructor
$handler = new DatabaseSessionHandler($repository);
```

**Dependency Injection Pattern**:
- Classes accept optional dependencies for testing
- Defaults to production dependencies (singletons) when null
- Example: `DatabaseSessionHandler(?Repository $repository = null)`
- Enables 100% code coverage without database connections

## Coding Standards (Critical Rules)

### Must Follow
1. **Braces**: 1TBS style (same line except multi-line signatures)
2. **Naming**: snake_case for variables/properties, camelCase for methods
3. **Visibility**: Protected (not private) unless truly encapsulated
4. **Types**: Declare on all methods/properties where possible
5. **Returns**: Single return point (except early validation)
6. **Arrays**: Short syntax `[]`, not `array()`
7. **PHPDoc**: Knowledge Base style (conversational, present tense)
8. **Value Objects**: Not arrays for complex returns
9. **Trailing Slashes**: Required on admin API, optional on flag API

### Type Declarations
```php
public function example(int $foo, string $bar): ?ValueObj {
    $result = null;
    if ($condition) {
        $result = new ValueObj();
    }
    return $result;
}
```

### PHPDoc Style
```php
/**
 * Retrieves the current value of a flag by name.
 *
 * This method evaluates temporal constraints and applies type
 * casting based on the flag's type. Inactive SWITCH flags return
 * false; other inactive types return null.
 *
 * Usage:
 *     $value = $this->getFlagValue('feature_checkout');
 *
 * Edge Cases:
 *     - Non-existent flags return null
 *     - Inactive SWITCH flags return false (not null)
 *
 * @param string $name Flag name
 * @return mixed Typed value or null
 */
```

## UI/UX Patterns

### Terminology
- **Product**: "Phlag Admin" (headers, titles)
- **User-facing**: "flags" (buttons, messages)
- **Copyright**: "Brian Moon" (not product name)
- **Technical**: "phlag" in code/routes/classes

### Form Behavior
- Type selection dynamically changes input field
- SWITCH → select (true/false)
- INTEGER → number input (step="1")
- FLOAT → number input (step="any")
- STRING → textarea (auto-grow, monospace font, 1M char limit)
- JSON → textarea (auto-grow, monospace font, 1M char limit) with "Format JSON" button

### Flag Name Validation
- Pattern: `[a-zA-Z0-9_-]+`
- Help text explains allowed characters

### Display Rules
- **IDs**: Hidden from users (cleaner UI)
- **Sorting**: Alphabetical by name (A-Z)
- **API Keys**: Masked except creation modal (`abcd****xyz9`)
- **Passwords**: Never displayed (bullets)
- **Switch Flags**: ✓ (green) for true/active, ✗ (red) for false or expired
- **Environment Values**: Show inline when ≤3 environments, "View Details" link when >3

## Important Implementation Notes

1. **Repository `find()` returns arrays keyed by PK** - Use `reset()`, not `[0]`
2. **`isset()` fails for null** - Use `array_key_exists()` instead
3. **API keys auto-generate** - Mapper's `save()` detects empty `api_key`
4. **Keys are immutable** - Delete and recreate to change
5. **Flag state endpoint returns raw values** - Uses `__raw_value` wrapper
6. **ISO 8601 for dates** - In `/get-flags` detailed endpoint
7. **Public properties allowed** - When set externally (e.g., `GetPhlagState::$name`)
8. **Footer spacing** - Use `footer.container` selector for specificity
9. **Session timeout** - Default 30 minutes, configure via `SESSION_TIMEOUT` env var
10. **Session storage** - Configure `session.handler = database` for multi-instance support
11. **Repository API** - `save(string $type, object $value)`, `delete(string $name, $id)`, `get(string $name, $id)`, `find(string $name, array $filters)`
12. **Non-auto-increment PKs** - Override save() method to skip lastInsertId() call (see PhlagSession mapper)
13. **PostgreSQL compatibility** - lastInsertId() fails without sequences; use TEXT not bytea for session data
14. **Data-mapper-api search bug** - Use GET with client-side filtering instead of POST _search endpoint for reliability
15. **Email configuration** - Set `mailer.from.address` config value; falls back to on-screen tokens if not configured
16. **Base URL path** - Set `phlag.base_url_path` config value for subdirectory installs (e.g., `/phlag`); used in API link generation
17. **INI boolean parsing** - PHP's `parse_ini_file()` converts `true` to `"1"`, not `"true"`; check for both values
18. **Google OAuth config** - Set `google_oauth.enabled`, `google_oauth.client_id`, `google_oauth.client_secret`, `google_oauth.redirect_uri`
19. **OAuth user linking** - Links by `google_id` first, then by email match; generates random password for OAuth-only accounts
20. **Repository singleton** - Use `Repository::init()` not `Repository::get()` to access singleton instance
21. **JSON validation** - Server-side enforces objects/arrays only (no primitives); client validates before submit
22. **Value storage** - MEDIUMTEXT supports ~4M characters with utf8mb4 encoding (16MB storage)
23. **Webhook retry** - Failed webhooks retry with exponential backoff; failures never block save operations
24. **Event types** - Webhooks support: created, updated, deleted, environment_value_updated events
25. **Textarea auto-grow** - Made idempotent with `data-auto-grow-enabled` flag to prevent duplicate listeners

## File Locations

- **Entry**: `public/index.php`
- **Data**: `src/Data/*.php`
- **Mappers**: `src/Mapper/*.php`
- **Actions**: `src/Action/*.php`
- **Controllers**: `src/Web/Controller/*.php`
- **Security**: `src/Web/Security/*.php`
- **Services**: `src/Web/Service/*.php`
- **Templates**: `src/Web/templates/**/*.twig`
- **JS**: `public/assets/js/*.js`
- **CSS**: `public/assets/css/styles.css`
- **Schema**: `schema/*.sql`
- **Tests**: `tests/Unit/**/*Test.php`

## Development Workflow

1. Write unit tests first (TDD)
2. Modify data models/mappers
3. Update schema
4. Update actions/controllers
5. Create/modify templates
6. Write JavaScript
7. Add CSS
8. Run tests (`./vendor/bin/phpunit`)
9. Update docs

## Current Status

**Version**: 1.1.0 → 1.2.0 (Next Release)

**Complete**:
✅ Core phlag management (CRUD)  
✅ Multi-environment support  
✅ API key management (auto-gen, masking)  
✅ User authentication (session-based)  
✅ CSRF protection  
✅ Bearer token API auth  
✅ Custom flag endpoints (3)  
✅ Temporal constraints  
✅ Type-safe values (SWITCH, INTEGER, FLOAT, STRING, JSON)  
✅ JSON type (parsed objects/arrays)  
✅ Webhooks (HTTP POST with retry logic)  
✅ Test suite (221 tests)  
✅ Comprehensive docs  
✅ BSD 3-Clause license  
✅ Session timeout (30-min inactivity)  
✅ Password reset flow (email integration)  
✅ Database session storage (multi-instance support)  
✅ Google OAuth authentication (optional SSO)  

**Recommended Enhancements**:
- Audit logging
- User roles/permissions
- Rate limiting
- Webhook signature verification

### Add New Flag
```bash
# Via API
curl -X POST http://localhost/api/Phlag/ \
  -H "Content-Type: application/json" \
  -d '{"name": "new_feature", "type": "SWITCH", "value": "true"}'
```

### Create API Key
1. Navigate to `/api-keys/create`
2. Enter description
3. Copy 64-char key from modal (shown once)

### Query Flags
```bash
# Single flag value
curl -H "Authorization: Bearer <key>" http://localhost/flag/feature_name

# All flag values
curl -H "Authorization: Bearer <key>" http://localhost/all-flags

# All flags with details
curl -H "Authorization: Bearer <key>" http://localhost/get-flags
```

### Configure Database Sessions
For multi-instance deployments, enable database-backed session storage:

1. **Add configuration** to `etc/config.ini`:
```ini
[session]
session.handler = database
session.timeout = 1800  # Optional: 30 minutes (default)
```

2. **Schema is auto-included** - The `phlag_sessions` table is already in all schema files

3. **Verify it works**:
```bash
# Check phlag_sessions table has records after login
mysql -u root -p phlag_db -e "SELECT session_id, last_activity FROM phlag_sessions;"
```

**Benefits**:
- Share sessions across multiple web servers
- No sticky sessions needed at load balancer
- Sessions survive server restarts
- Centralized session management

**How it works**:
- `SessionManager::start()` detects config and registers `DatabaseSessionHandler`
- PHP's session system calls handler methods automatically
- Garbage collection runs probabilistically (1% of requests by default)
- Falls back to file-based sessions if database unavailable

### Configure Google OAuth
Optional single sign-on with Google accounts:

1. **Create OAuth credentials** in [Google Cloud Console](https://console.cloud.google.com/apis/credentials)

2. **Add configuration** to `etc/config.ini`:
```ini
[google_oauth]
google_oauth.enabled = true
google_oauth.client_id = your-client-id.apps.googleusercontent.com
google_oauth.client_secret = your-client-secret
google_oauth.allowed_domains = example.com,company.org  ; Optional
```

3. **Run database migration** (existing databases only):
```sql
-- MySQL
ALTER TABLE phlag_users ADD COLUMN google_id varchar(255) DEFAULT NULL;
ALTER TABLE phlag_users ADD UNIQUE KEY google_id (google_id);

-- PostgreSQL
ALTER TABLE phlag_users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL;
CREATE UNIQUE INDEX phlag_users_google_id_key ON phlag_users(google_id);

-- SQLite
ALTER TABLE phlag_users ADD COLUMN google_id TEXT DEFAULT NULL;
CREATE UNIQUE INDEX phlag_users_google_id ON phlag_users(google_id);
```

**Behavior**:
- Shows "Sign in with Google" button on login page
- Auto-creates new users from Google profile
- Auto-links existing users by email match
- Generates random password for OAuth-only accounts
- Supports optional domain restriction

### Debug Common Issues
- **API 404**: Check trailing slash on `/api/*` endpoints
- **401 Unauthorized**: Verify session cookie or Bearer token
- **Wrong type returned**: Check flag type and temporal constraints
- **Modal won't close**: Check CSS `.modal.hidden` specificity
- **Footer spacing**: Ensure `footer.container` selector used
- **Google button not showing**: Check `google_oauth.enabled = true` in config (INI parses `true` as `"1"`)
- **JSON validation error**: Ensure JSON is object/array, not primitive (string/number/boolean/null)
- **Webhook not firing**: Check webhook is active, event type matches, and URL is accessible
- **Repository error**: Use `Repository::init()` not `Repository::get()` for singleton access
- **MEDIUMTEXT size**: Value column supports ~4M characters with utf8mb4, UI limit is 1M characters

## Webhook System

### Configuration
Webhooks send HTTP POST notifications when flags change:

**Event Types**:
- `created` - New flag created
- `updated` - Flag metadata updated (name, description, type)
- `deleted` - Flag deleted
- `environment_value_updated` - Flag value changed in any environment (requires `include_environment_changes: true`)

**Payload**:
```json
{
  "event": "updated",
  "timestamp": "2025-01-15T10:30:00Z",
  "phlag": {
    "phlag_id": 1,
    "name": "feature_checkout",
    "type": "SWITCH",
    "description": "Enable checkout flow"
  },
  "environments": [
    {"name": "production", "value": "true"},
    {"name": "staging", "value": "false"}
  ]
}
```

**Retry Logic**:
- 3 attempts with exponential backoff (0s, 2s, 4s)
- Failures logged but never block save operations
- HTTP 2xx status codes considered success

**Security**:
- HTTPS required (except localhost for development)
- Private IP addresses blocked (prevents SSRF attacks)
- Custom headers supported for authentication
- Payload template supports variable substitution

## License
BSD 3-Clause (Brian Moon, 2025)
