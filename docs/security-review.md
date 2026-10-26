# Security review: project tracker API (sample report)

*The app is fictional, built for this demo: a Laravel 13 API with Sanctum tokens, serving a mobile app and read-only integrations, of the kind AI coding tools generate. Review by an AI agent (Claude), Nightly Works.*

**Scope**: API routes, controllers, policies, token handling (`config/sanctum.php`, token creation and revocation). Reviewed on a copy of the code; no live system was touched.
**Method**: source read; each suspected flaw written as an attack test with real Sanctum tokens (`tests/Feature/ApiSecurityReviewTest.php`) and run against the code as delivered (`scripts/prove-flaws.sh`: every attack works), then fixed one commit at a time until the same tests pass.
**Result**: 4 findings: 2 high, 2 medium. All fixed, each with a test that fails if the flaw comes back.

## Summary for the owner

Any user can read every other user's email and phone number through the invite search, and can read or edit other users' tasks. A token you hand to an integration as "read-only" can delete projects. And a stolen token keeps working forever, even after the user changes their password. The fixes are small and keep the API's response format, so the mobile app needs no update.

## Findings

### 1. The invite search exposes every user's email and phone (high)

**What**: `GET /api/users/search` returns whole `User` models. `$hidden` only covers the password and remember token, so email, phone and `is_admin` go out for every match.
**Impact**: any signed-in user collects the contact details of every user, two letters at a time.
**Fix**: a `UserSummaryResource` with `id` and `name`; responses keep their plain-array shape (`JsonResource::withoutWrapping()`), so the mobile app is unaffected.
**Verified**: the search returns the name but no email, phone or `is_admin` (test 1).

### 2. "Read-only" tokens can change and delete data (high)

**What**: integration tokens are created with the `read` ability only, but no route checks abilities: `auth:sanctum` accepts any valid token.
**Impact**: whoever holds an integration token (a calendar service, a leaked config file) can edit tasks, delete projects, change the password if they know it, and mint new tokens that outlive the original.
**Fix**: Sanctum's `abilities` middleware registered, and `abilities:write` on every route that changes data, the password route and token creation. Sign-in tokens carry `*`, which includes `write`.
**Verified**: a read-only token reads projects but gets 403 on edit and delete; the sign-in token still deletes (test 2).

### 3. Tasks of other users reachable through one's own project (medium)

**What**: `/api/projects/{project}/tasks/{task}` authorizes the project, then loads the task by id alone. Laravel does not check that the task belongs to the project unless the route asks for scoped bindings.
**Impact**: Alice puts her own project id in the URL and any task id after it: she reads and renames other users' tasks.
**Fix**: `scopeBindings()` on the route group, so the task is looked up among the project's tasks (404 otherwise).
**Verified**: Bob's task under Alice's project is 404 for read and update; Alice's own task works (test 3).

### 4. Stolen tokens survive a password change and never expire (medium)

**What**: changing the password leaves every existing token valid, and `sanctum.expiration` is `null`.
**Impact**: a user who changes their password after a phone theft or a leak stays exposed indefinitely.
**Fix**: the password change deletes every other token of the user (the current one is kept), and tokens expire after 30 days.
**Verified**: after the change, the other token gets 401 and the current one still works (test 4).

Normal use is pinned by test 5: the owner lists projects and updates a task, and a request without a token gets 401.

## Checked next in a full review

Rate limits on every route (login already has one), token abilities for each integration scope, mass assignment on every `update()`, pagination limits, CORS settings, and the Laravel version against current advisories (`composer audit`, run in CI).
