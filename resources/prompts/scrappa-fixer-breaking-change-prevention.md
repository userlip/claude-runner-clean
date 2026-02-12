# Scrappa Fixer - Breaking Change Prevention Rules

## CRITICAL: Never Break Existing API Contracts

Scrappa has paying customers who depend on our API responses. **Any breaking change will cause customer integrations to fail**, resulting in:
- Customer applications breaking in production
- Loss of trust and potential churn
- Support tickets and emergency hotfixes
- Damage to Scrappa's reputation

---

## What Constitutes a Breaking Change

The following changes are **BREAKING** and **MUST NEVER** be made without:
1. Creating a new API version (e.g., `/api/v2/...`)
2. Explicit customer communication
3. A migration period with deprecation warnings

### Response Structure Changes

| Change Type | Example | Breaking? |
|-------------|---------|-----------|
| Wrapping response in new object | `{ "reviews": [...] }` → `{ "data": { "reviews": [...] } }` | **YES - CRITICAL** |
| Renaming top-level fields | `{ "results": [...] }` → `{ "items": [...] }` | **YES - CRITICAL** |
| Changing array to object | `[...]` → `{ "data": [...] }` | **YES - CRITICAL** |
| Removing fields | Keeping field but returning null | No |
| Removing fields | Removing key entirely | **YES - CRITICAL** |
| Changing field types | `"id": 123` → `"id": "123"` | **YES - CRITICAL** |
| Changing date formats | `"2024-01-01"` → `"2024-01-01T00:00:00Z"` | **YES - HIGH** |
| Changing pagination structure | `{ "page": 1 }` → `{ "pagination": { "page": 1 } }` | **YES - CRITICAL** |

### Request/Parameter Changes

| Change Type | Breaking? |
|-------------|-----------|
| Making optional params required | **YES - CRITICAL** |
| Removing supported parameters | **YES - CRITICAL** |
| Changing parameter names | **YES - CRITICAL** |
| Changing default values | **YES - HIGH** |
| Adding new required params | **YES - CRITICAL** |

### Status Code Changes

| Change Type | Breaking? |
|-------------|-----------|
| Changing success status (200→201) | **YES - MEDIUM** |
| Changing error status codes | **YES - HIGH** |
| Returning errors where success was before | **YES - CRITICAL** |

---

## The Golden Rule

> **If a customer wrote code against the current API response, would their code still work?**
>
> If the answer is "no" or "maybe", **IT IS A BREAKING CHANGE**.

### Code Examples of Breaking Changes

**Example 1: The "formatReviewsResults" Breaking Change**

Before (what customers coded against):
```php
$response = $client->get('/api/trustpilot/reviews?domain=example.com');
$data = json_decode($response, true);

// Customer code:
foreach ($data['reviews'] as $review) {  // This line BREAKS after the change
    echo $review['title'];
}
```

After the breaking change:
```php
$response = $client->get('/api/trustpilot/reviews?domain=example.com');
$data = json_decode($response, true);

// Customer code now fails with:
// "Undefined array key 'reviews'"
// Because the new structure is $data['data']['reviews']
```

**Example 2: Changing Response Wrapper**

Before:
```json
{
  "reviews": [...],
  "pagination": { ... }
}
```

After (BREAKING):
```json
{
  "success": true,
  "data": {
    "reviews": [...],
    "pagination": { ... }
  },
  "message": "Reviews retrieved successfully"
}
```

---

## What You CAN Do (Non-Breaking Changes)

The following are **SAFE** and do not require a new API version:

### Additive Changes
- ✅ Add NEW fields to response (existing code ignores unknown fields)
- ✅ Add NEW optional parameters
- ✅ Add NEW endpoints
- ✅ Add NEW API versions (e.g., `/api/v2/...`)

### Bug Fixes
- ✅ Fix broken functionality to match documented behavior
- ✅ Fix 500 errors to return proper 4xx errors
- ✅ Fix incorrect data (wrong values, not structure)

### Performance/Internal Changes
- ✅ Optimize query performance
- ✅ Change internal implementation
- ✅ Add caching (as long as responses are identical)

---

## Pre-Commit Checklist for API Changes

Before committing ANY change to API endpoints, verify:

- [ ] Response structure is identical to before (same nesting, same field names)
- [ ] All fields that existed before still exist at the same path
- [ ] Field types haven't changed (string→int, array→object, etc.)
- [ ] Array structures are preserved (not wrapped in objects)
- [ ] Tests verify the EXACT response structure customers depend on
- [ ] If adding a wrapper/object, it's ADDITIVE only (new field alongside old)

---

## When You Think "This Should Be Standardized"

Common rationalization: *"But we should wrap all responses in a standard format with success/data/message!"*

**Reality:** Standardization is good, but NOT at the cost of breaking existing customers.

### Correct Approach for Standardization

1. **Create a new API version** (e.g., `/api/v2/trustpilot/reviews`)
2. **Implement the new standard** in v2
3. **Document the migration path** for customers
4. **Support both versions** during a transition period
5. **Communicate deprecation** timeline to customers
6. **Eventually retire v1** after sufficient notice

### Wrong Approach (NEVER DO THIS)

1. ❌ Change the response format in the existing endpoint
2. ❌ Update tests to match the new format
3. ❌ Hope customers don't notice

---

## Testing Requirements for API Changes

### Response Structure Tests

Every API endpoint MUST have tests that verify:

```php
// EXAMPLE: Test that verifies exact response structure
public function test_reviews_response_structure()
{
    $response = $this->getJson('/api/trustpilot/reviews?domain=example.com');

    $response->assertOk();

    $data = $response->json();

    // CRITICAL: Verify top-level structure hasn't changed
    $this->assertArrayHasKey('reviews', $data);
    $this->assertArrayHasKey('pagination', $data);
    $this->assertArrayHasKey('businessUnit', $data);

    // CRITICAL: Verify reviews is at top level, not wrapped
    $this->assertIsArray($data['reviews']);

    // CRITICAL: Verify nested structure within reviews
    if (count($data['reviews']) > 0) {
        $review = $data['reviews'][0];
        $this->assertArrayHasKey('id', $review);
        $this->assertArrayHasKey('title', $review);
        $this->assertArrayHasKey('content', $review);
    }
}
```

### Backward Compatibility Tests

```php
// EXAMPLE: Test that simulates customer code
public function test_existing_customer_code_would_work()
{
    $response = $this->getJson('/api/trustpilot/reviews?domain=example.com');
    $data = $response->json();

    // Simulate exactly what customer code does
    $reviews = $data['reviews'];  // This MUST work
    $pagination = $data['pagination'];  // This MUST work

    // If this test passes, customer code won't break
    $this->assertIsArray($reviews);
    $this->assertIsArray($pagination);
}
```

---

## Red Flags - STOP and Reconsider

If you find yourself thinking or doing any of these, **STOP** and reconsider:

| Red Flag | Why It's Dangerous |
|----------|-------------------|
| "I'll just wrap this in a 'data' object for consistency" | Breaks all existing integrations |
| "The tests are failing, I'll update them to match" | Tests exist to catch breaking changes |
| "This structure is cleaner/better" | Clean doesn't matter if it breaks customers |
| "It's just a small structural change" | Small changes break code just as much |
| "Customers can update their code" | They won't know until production breaks |
| "I'll add success/data/message to all responses" | Standardization requires versioning |
| "The old structure was wrong" | Being "right" isn't worth breaking customers |

---

## If You Must Make a Breaking Change

Sometimes breaking changes are necessary (security fixes, deprecated dependencies). When unavoidable:

1. **Create a new endpoint version** (`/api/v2/...`)
2. **Keep the old endpoint working** exactly as before
3. **Add deprecation headers** to old endpoint responses
4. **Document the migration** clearly
5. **Notify customers** via email/changelog
6. **Provide migration examples** showing before/after code
7. **Set a deprecation timeline** (minimum 6 months notice)

---

## Summary

**Your job is to fix issues WITHOUT breaking existing customers.**

- ✅ Fix bugs that match documented behavior
- ✅ Add new features additively
- ✅ Optimize performance without changing responses
- ❌ NEVER change response structure in existing endpoints
- ❌ NEVER rename fields in existing endpoints
- ❌ NEVER wrap responses in new objects
- ❌ NEVER change field types

**When in doubt:** Create a new API version. It's better to maintain two versions than to break customer integrations.
