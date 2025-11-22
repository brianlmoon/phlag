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

### Naming Convention
- Primary keys: `{table_name}_id`
- Sort order: Alphabetical by name (A-Z)

## Security Features

✅ **Passwords**: Bcrypt hashing, 8-char minimum, confirmation on create  
✅ **API Keys**: Crypto-secure 64-char generation, one-time display, masking  
✅ **CSRF**: 256-bit tokens, timing-safe comparison, replay protection  
✅ **Sessions**: Regeneration on login, destruction on logout, 30-min timeout  
✅ **XSS**: HTML escaping in templates and JS  

## Testing

**Suite**: 104 tests, 100% pass rate

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
10. **Email configuration** - Set `mailer.from.address` config value; falls back to on-screen tokens if not configured
11. **Base URL path** - Set `phlag.base_url_path` config value for subdirectory installs (e.g., `/phlag`); used in API link generation

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
✅ Test suite (104 tests)  
✅ Comprehensive docs  
✅ BSD 3-Clause license  
✅ Session timeout (30-min inactivity)  
✅ Password reset flow (email integration)  

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

### Debug Common Issues
- **API 404**: Check trailing slash on `/api/*` endpoints
- **401 Unauthorized**: Verify session cookie or Bearer token
- **Wrong type returned**: Check flag type and temporal constraints
- **Modal won't close**: Check CSS `.modal.hidden` specificity
- **Footer spacing**: Ensure `footer.container` selector used

## License
BSD 3-Clause (Brian Moon, 2025)
