# Phlag v2.0 Quick Start Guide

## First-Time Setup

Now that you've installed the v2.0 database schema, follow these steps to get started:

### 1. Create Your First User

Navigate to your Phlag installation (e.g., `http://localhost`). Since the database is fresh, you'll be redirected to the first-user creation page.

1. Go to `/first-user`
2. Create your admin account
3. You'll be automatically logged in

### 2. Create Environments

**Important:** Flags now require environments to store values. Create your environments first.

1. Navigate to `/environments` or click "Environments" in the navigation
2. Click "Create New Environment"
3. Create environments like:
   - **production** (sort_order: 10)
   - **staging** (sort_order: 20)
   - **development** (sort_order: 30)

The `sort_order` determines the display order in the UI (lower numbers appear first).

### 3. Create API Keys

You'll need an API key to query flag values from your applications.

1. Navigate to `/api-keys`
2. Click "Create New API Key"
3. Enter a description (e.g., "Production App Key")
4. **Copy the generated key immediately** - it's only shown once!

### 4. Create Your First Flag

1. Navigate to `/flags`
2. Click "Create New Flag"
3. Fill in the form:
   - **Name:** `feature_new_checkout` (cannot be changed after creation)
   - **Description:** "Enable new checkout flow"
   - **Type:** `SWITCH` (cannot be changed after creation)
4. Configure environment values:
   - **development:** `true`
   - **staging:** `true`
   - **production:** `false` (or leave unconfigured)
5. Optionally set temporal constraints (start/end dates)
6. Click "Create Flag"

## Using Flags in Your Application

### Query a Single Flag

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/flag/production/feature_new_checkout
```

Returns: `false` (or `null` if not configured)

### Query All Flags for an Environment

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/all-flags/production
```

Returns:
```json
{
  "feature_new_checkout": false,
  "max_items": 100,
  "price_multiplier": 1.5
}
```

### Query Flags with Metadata

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/get-flags/production
```

Returns:
```json
[
  {
    "name": "feature_new_checkout",
    "type": "SWITCH",
    "value": false,
    "start_datetime": null,
    "end_datetime": "2025-12-31T23:59:59-05:00"
  }
]
```

## Key Differences from v1.x

### Breaking Changes

1. **Environment Required:** All flag retrieval endpoints now require an environment parameter
   - Old: `/flag/my_flag` ❌
   - New: `/flag/production/my_flag` ✅

2. **No Global Values:** Flags no longer have a single "value" - they have environment-specific values
   - Each flag can have different values in production, staging, development, etc.

3. **Immutable Fields:** After creation, a flag's `name` and `type` cannot be changed
   - This prevents breaking API contracts
   - Delete and recreate if you need to change these

### New Features

1. **Environment-Specific Values:** Configure different values per environment
2. **Temporal Constraints Per Environment:** Each environment value can have its own start/end dates
3. **NULL Semantics:**
   - No environment value = `null` (not configured)
   - Environment value with NULL = inactive value (explicitly disabled)
   - SWITCH returns `false` when inactive
   - INTEGER/FLOAT/STRING return `null` when inactive

## Common Workflows

### Gradual Rollout

1. Create flag with type `SWITCH`
2. Set value to `false` in production
3. Set value to `true` in staging for testing
4. After testing, update production value to `true`

### Scheduled Feature Launch

1. Create flag with desired value
2. Set `start_datetime` to launch date/time
3. Flag will automatically activate at that time

### Temporary Feature

1. Create flag with desired value
2. Set `start_datetime` and `end_datetime`
3. Flag will activate and deactivate automatically

### Configuration Values

Use INTEGER, FLOAT, or STRING types for configuration:

```php
// In your application
$api_url = "http://localhost";
$api_key = "your-api-key-here";
$env = "production";

$response = file_get_contents(
    "$api_url/flag/$env/max_concurrent_requests",
    false,
    stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer $api_key"
        ]
    ])
);

$max_requests = json_decode($response); // Returns: 100 (integer)
```

## Troubleshooting

### "Environment not found" Error

Make sure you've created environments in the admin panel first. The environment name in the URL must exactly match an environment name in the database.

### Flag Returns `null`

This means one of:
1. The flag doesn't exist
2. The flag isn't configured for that environment yet
3. The environment value is explicitly set to NULL (disabled)

### API Returns 401 Unauthorized

Check that:
1. You're sending the `Authorization: Bearer <key>` header
2. The API key is valid and exists in the database
3. The API key wasn't deleted

### Changes Not Reflected

The API returns real-time values - there's no caching. If values aren't updating:
1. Check you're querying the correct environment
2. Verify the value was saved (view the flag in the admin panel)
3. Check temporal constraints (start/end dates)

## Next Steps

1. Read `AGENTS.md` for detailed technical documentation
2. Check `ENV_FEATURE.md` for the complete v2.0 implementation plan
3. Review existing flags and migrate them to environment-specific values
4. Update your applications to include environment in API calls

## Migration from v1.x

If you have existing applications using v1.x endpoints:

1. **Update API Calls:** Add environment parameter to all flag queries
2. **Reconfigure Flags:** Old flag values were lost in migration - reconfigure with environment values
3. **Test Thoroughly:** The NULL semantics changed - test edge cases

## Support

For questions or issues, refer to:
- `README.md` - General project documentation
- `AGENTS.md` - AI agent quick reference
- `ENV_FEATURE.md` - v2.0 feature specification
