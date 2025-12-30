# Phlag - AI Agent Quick Reference

## What It Is
Feature flag management system (PHP 8.0+) with RESTful API and web admin UI. Manages typed flags (SWITCH, INTEGER, FLOAT, STRING) with temporal scheduling.

## Stack
- **Backend**: PHP 8.0+, Twig 3.x, PageMill Router, DealNews DataMapper
- **Frontend**: Vanilla JS (XMLHttpRequest), CSS3
- **Database**: MySQL (primary), PostgreSQL, SQLite

## Key Architecture

### Data Layer
- **Value Objects**: `Phlag`, `PhlagApiKey`, `PhlagUser` (in `src/Data/`)
- **Mappers**: Auto-generate API keys (64-char), hash passwords (bcrypt)
- **Repository**: Singleton with `init()`, auto-registers mappers

### Action Layer (Custom Endpoints)
- **FlagValueTrait**: Shared temporal logic + type casting
- **GetPhlagState** (`/flag/{name}`): Single flag value (typed scalar)
- **GetAllFlags** (`/all-flags`): All flags as key-value object
- **GetFlags** (`/get-flags`): All flags with metadata (ISO 8601 dates)

### Web Layer
- **Controllers**: BaseController (auth/CSRF), Auth, Phlag, ApiKey, User
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

### First-Time Setup
1. Empty `phlag_users` table triggers redirect to `/first-user`
2. Create initial user (username, full_name, password)
3. Auto-login and redirect to dashboard

## Critical API Details

### Admin API (Session Auth Required)
```
GET/POST/PUT/DELETE /api/Phlag/         # CRUD for flags
GET/POST/PUT/DELETE /api/PhlagApiKey/   # CRUD for API keys
GET/POST/PUT/DELETE /api/PhlagUser/     # CRUD for users
```
**Important**: Trailing slashes required, returns 401 if not logged in

### Flag State API (Bearer Auth Required)
```
GET /flag/{name}      # Returns: true, 100, 1.5, "text", false, or null
GET /all-flags        # Returns: {"flag1": true, "flag2": 100, ...}
GET /get-flags        # Returns: [{name, type, value, start, end}, ...]
```
**Trailing slash optional**

### Temporal Logic
Flag is **active** when:
- `start_datetime` is NULL or ≤ now
- `end_datetime` is NULL or ≥ now

**Inactive behavior**:
- SWITCH → `false`
- INTEGER/FLOAT/STRING → `null`

### Type Casting
- SWITCH → boolean
- INTEGER → int
- FLOAT → float
- STRING → string (no casting)

## Database Schema

### Tables (all prefixed `phlag_`)
- **phlags**: `phlag_id`, name, type, value, start_datetime, end_datetime
- **phlag_api_keys**: `plag_api_key_id`, description, api_key (64 chars)
- **phlag_users**: `phlag_user_id`, username, full_name, password (bcrypt)
- **phlag_sessions**: `session_id`, session_data, last_activity, create_datetime

### Naming Convention
- Primary keys: `{table_name}_id`
- Sort order: Alphabetical by name (A-Z)

## Security Features

✅ **Passwords**: Bcrypt hashing, 8-char minimum, confirmation on create  
✅ **API Keys**: Crypto-secure 64-char generation, one-time display, masking  
✅ **CSRF**: 256-bit tokens, timing-safe comparison, replay protection  
✅ **Sessions**: Regeneration on login, destruction on logout, 30-min timeout  
✅ **Session Storage**: Database-backed sessions for multi-instance support  
✅ **XSS**: HTML escaping in templates and JS  

## Testing

**Suite**: 165 tests, 100% pass rate

```bash
./vendor/bin/phpunit                    # Run all tests
./vendor/bin/phpunit --testdox          # Detailed output
./vendor/bin/phpunit --filter testName  # Specific test
```

**Coverage**:
- `FlagValueTraitTest.php` (21 tests) - Temporal + type casting
- `GetPhlagStateTest.php` (15 tests) - Single flag endpoint
- `GetAllFlagsTest.php` (10 tests) - Bulk flag endpoint
- `GetFlagsTest.php` (14 tests) - Detailed flag endpoint
- `CsrfTokenTest.php` (17 tests) - CSRF protection
- `SessionManagerTest.php` (21 tests) - Session timeout and lifecycle
- `DatabaseSessionHandlerTest.php` (16 tests) - Database session storage

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
- STRING → text input

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

**Version**: 1.4.0 (Production Ready)

**Complete**:
✅ Core phlag management (CRUD)  
✅ API key management (auto-gen, masking)  
✅ User authentication (session-based)  
✅ CSRF protection  
✅ Bearer token API auth  
✅ Custom flag endpoints (3)  
✅ Temporal constraints  
✅ Type-safe values  
✅ Test suite (165 tests)  
✅ Comprehensive docs  
✅ BSD 3-Clause license  
✅ Session timeout (30-min inactivity)  
✅ Password reset flow (email integration)  
✅ Database session storage (multi-instance support)  

**Recommended Enhancements**:
- Audit logging
- User roles/permissions
- Rate limiting

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

### Debug Common Issues
- **API 404**: Check trailing slash on `/api/*` endpoints
- **401 Unauthorized**: Verify session cookie or Bearer token
- **Wrong type returned**: Check flag type and temporal constraints
- **Modal won't close**: Check CSS `.modal.hidden` specificity
- **Footer spacing**: Ensure `footer.container` selector used

## License
BSD 3-Clause (Brian Moon, 2025)
