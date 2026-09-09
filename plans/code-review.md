# Bug, security, and maintainability review

Date: 2026-09-09. Companion: [implementation plan](remediation-plan.md).

## Scope and evidence

Reviewed application routes, tenancy, invitation lifecycle, authentication/settings, selected frontend controllers, schema definitions, relevant tests, and deployment/configuration files. Application code was not changed. This is a targeted source review, not a complete penetration test or a production infrastructure audit.

Runtime observed: PHP 8.4.25, Laravel 13.26.1, Fortify 1.38.0, PHPUnit 13.3.1. Prefer installed versions over older version claims in project instructions.

Checks performed:

- `composer audit --locked --no-interaction`: no known vulnerability advisories found. This does not audit application logic or vendored JavaScript.
- `composer check-platform-reqs --lock`: passed on the current CLI runtime.
- `composer validate --no-check-publish`: valid, with a duplicate `scripts.test` warning.
- `vendor/bin/phpunit tests/Feature/Http/Controllers/AcceptInvitationControllerTest.php tests/Unit/Http/Requests/SendInvitationRequestTest.php tests/Feature/Http/Controllers/Settings/RecoveryCodesControllerTest.php`: **26 tests, 67 assertions, passed**.
- An initial Artisan test invocation with `--no-interaction` failed because it was forwarded to PHPUnit; the direct PHPUnit invocation above succeeded.

The full suite was deliberately not run after discovering R01. No production database, email transport, deployment, or exploit was exercised. Findings below are source-confirmed unless explicitly classified as conditional or requiring integration verification. The passing tests are baseline coverage, not reproductions of the newly identified defects.

## Priority summary

| ID | Priority | Finding | Evidence classification |
| --- | --- | --- | --- |
| R01 | P1 | Tests can delete real tenant SQLite databases | Confirmed destructive code |
| R03 | P1 | Tenant selection accepts unrelated hostnames | Confirmed application gap; exploitability depends on ingress |
| R04 | P2 | Expired/accepted invitations cannot reliably be reissued | Confirmed validation/schema mismatch |
| R05 | P2 | CLI tenant invitation URL points at main database | Confirmed missing tenant URL context |
| R06 | P2 | Invitation acceptance is not atomic | Confirmed failure/race window |
| R07 | P2 | Mixed-case stored email conflicts with lowercase login | Confirmed on case-sensitive email lookup, including SQLite |
| R08 | P2 | Two-factor pages fail for unconfigured users; weak code validation | Confirmed missing state/type guards |
| R09 | P2 | OTP input events do not update submitted code | Confirmed frontend state defect |
| R10 | P2 | Blocked users cannot log out | Confirmed middleware ordering |
| R11 | P1 | Tenant databases are not shared across Deployer releases | Confirmed configuration omission in tenant mode |
| R12 | P1 investigation | Tenant authorization runs before session initialization | Confirmed ordering problem; security impact needs real-session tests |
| R13 | P2 hardening | User serialization includes encrypted 2FA credentials | Confirmed serialization gap; no public leak endpoint identified |
| R14 | P2 | Default log stack references an unavailable Bugsnag driver | Confirmed dependency/config mismatch |

P1: address first because of data loss/security exposure. P2: normal-priority correctness or defense-in-depth work. R12 is a verification/architecture task, not a claim of a demonstrated cross-tenant exploit.

## Findings

### R01 — Tests delete files in the actual application database directory

**Evidence:** `tests/Feature/Commands/CreateUserCommandTest.php:352-364` enumerates `database/db/*.sqlite` and calls `@unlink()` on every match. `tests/Unit/Services/TenantDatabaseServiceTest.php:28-33` deletes a fixed `testtenant.sqlite` path. `tests/Unit/Middleware/ConnectToUserDatabaseTest.php:103-132,142-168,176-198` similarly creates/removes fixed paths.

Running these tests against a developer checkout containing tenant data can destroy that data. SQLite `:memory:` and `RefreshDatabase` do not isolate direct filesystem calls. Fixed names also collide across parallel workers.

**Fix:** give filesystem tests a unique temporary database root; restore the application database path and remove only files created inside that root. Fix this before running the full suite. Replace the no-op connection-failure test at middleware test lines 86-94 with meaningful coverage.

### R03 — Host validation does not establish tenant-domain membership

**Evidence:** `app/Services/TenantDatabaseService.php:17-26` returns the first hostname label regardless of suffix. `app/Http/Middleware/ConnectToUserDatabase.php:27-41` uses that label to select a database. `bootstrap/app.php:19-30` does not configure trusted hosts. Password reset is enabled in `config/fortify.php:149`.

If ingress forwards arbitrary hosts, `acme.unrelated.example` selects the same tenant as `acme.<configured-domain>`. Laravel also uses the request host for generated absolute URLs, creating a conditional password-reset-link poisoning risk. In single-database mode, the tenancy middleware bypass does not itself restrict hosts.

**Fix:** accept only the configured base host and explicitly supported tenant host format. Configure trusted hosts as defense in depth, including in single-database mode. Test malicious suffixes and password-reset notification URLs. Upstream host restrictions can reduce exposure but were not verified here.

### R04 — Reinvitation lifecycle contradicts the unique email index

**Evidence:** `app/Http/Requests/SendInvitationRequest.php:21` rejects any unaccepted row, even expired rows. `app/Models/Invitation.php:28-42` considers only unexpired/unaccepted rows pending. `InvitationController.php:18,36-38` lists and deletes only pending rows. `database/migrations/2026_03_14_000000_create_invitations_table.php:13` makes email unique across all invitation rows; `Invitation::createFor()` always inserts.

Two failures:

1. Let an invitation expire. A new invite is rejected, the old one disappears from the pending list, and the delete action will not remove it.
2. Retain an accepted invitation after its user is deleted. Validation permits reinviting that email, but insertion violates the unique index. `SendInvitationRequestTest.php:57-64` checks only validation and misses this failure.

**Fix:** align lifecycle with storage. The companion plan recommends one invitation row per email, rotating the token and expiry when reissuing an inactive row; reject active invitations and existing users. Preserve invalidation of old links.

### R05 — CLI invitations lose the selected tenant hostname

**Evidence:** `CreateUserCommand.php:49-68,93-95,100-124` switches the database, but does not pass tenant identity to the mailable. `app/Mail/InvitationMail.php:31` generates an ordinary absolute named route.

In CLI execution, the URL generator uses the application URL rather than inferring a host from the active database. A tenant invitation is stored in the tenant database but links to the main host, where its token cannot be found. Existing command tests fake email delivery without checking the rendered URL.

**Fix:** explicitly carry the selected tenant context into invitation URL generation. Preserve the named route path and configured scheme/port. Cover both new and existing tenant selections plus the current database.

### R06 — Account creation and invitation consumption can diverge

**Evidence:** `AcceptInvitationController.php:27-43` checks pending state, creates the user, and updates the invitation separately.

If invitation update fails after account insertion, the account persists while the link remains pending. Concurrent acceptance can pass both pending checks; the users email unique index prevents duplicate accounts, but the loser can receive an unhandled database exception. An account created through another path after invitation issuance causes the same duplicate-email failure.

**Fix:** atomically claim a still-pending invitation and create its user in one transaction on the active connection. Handle an already-existing email deterministically. Do not assume `lockForUpdate()` supplies row locking on SQLite.

### R07 — Email normalization differs between onboarding and login

**Evidence:** `config/fortify.php:50,65` configures email login and lowercasing. Fortify's installed `CanonicalizeUsername.php:19-22` lowercases the submitted email. `SendInvitationRequest.php:16-23`, `CreateUserCommand.php:102-106,145-149`, and `UpdateUserRequest.php:22-28` accept mixed-case email; `AcceptInvitationController.php:37` copies it unchanged. The SQLite users email column has no case-insensitive collation.

An account stored as `Person@Example.com` can fail login because Fortify searches for `person@example.com`. Case variants can also evade case-sensitive uniqueness checks. The self-service profile request already requires lowercase email, so behavior is inconsistent.

**Fix:** consistently normalize before validation and persistence. Inspect existing data for collisions before a backfill; never blindly lowercase two identities into the same unique email.

### R08 — Direct navigation to two-factor setup/recovery can produce 500 responses

**Evidence:** `Settings/RecoveryCodesController.php:12-17` decrypts recovery codes without checking for null. `Settings/ConfirmedTwoFactorController.php:14-20` generates a QR code/decrypts the secret without checking setup state. Their route group (`routes/web.php:61-66`) requires authentication/password confirmation but not a configured secret.

An authenticated, password-confirmed user without 2FA can directly visit these pages and hit decryption errors. `ConfirmTwoFactorRequest.php:14` accepts `required|min:6`, which does not constrain input to a six-character digit string; an array with six items can pass validation and reach a scalar OTP provider.

**Fix:** return a deliberate redirect or 404 for invalid setup states. Require a string of exactly six ASCII digits, preserving leading zeros. Add tests for disabled/setup-pending/enabled states and malformed input.

### R09 — Mobile/input-only OTP changes leave the hidden code stale

**Evidence:** `resources/views/components/form/otp-input.blade.php:21` binds `input` to `sanitizeInput`. `resources/js/controllers/otp_controller.js:51-53` changes only the visible input. Hidden code synchronization happens on keydown/paste paths (lines 66-98), not ordinary input events.

Virtual keyboards, autofill, or accessibility tools that produce input without the expected digit keydown can display digits while submitting an empty/old hidden code. The 100 ms delayed keydown update also permits immediate submission of stale data.

**Fix:** synchronize on every value change without the timer. Verify input-only events, typing followed immediately by submit, paste, backspace, and leading zero codes.

### R10 — Blocking also blocks the logout route

**Evidence:** `EnsureUserIsNotBlocked.php:13-16` returns 403 for every authenticated blocked user. It is appended to the whole web group (`bootstrap/app.php:26-29`), which Fortify uses (`config/fortify.php:106`).

A blocked user with an existing session cannot submit logout; the request is rejected before Fortify invalidates the session. A newly blocked user can remain stuck until cookies are cleared or the session expires.

**Fix:** allow the authenticated logout route through while retaining CSRF protection and denying all protected app actions. Consider rejecting blocked credentials in the login pipeline as a separate policy choice.

### R11 — Deployer release rotation does not preserve tenant databases

**Evidence:** `deploy.php:29-31` shares only `database/database.sqlite`. Tenant databases live in `database/db/` (`TenantDatabaseService.php:29-31`). The installed Deployer Laravel recipe shares `storage` and `.env`, not the tenant directory. Deployment also migrates only the default connection; no tenant migration loop is defined in this project configuration.

With per-tenant databases enabled, a new release does not see the old release's tenant files. Old release cleanup can ultimately delete them. Tenant schema changes also need explicit deployment handling.

**Fix:** preserve the tenant directory as shared persistent data, and define migration/template maintenance for tenant mode. Coordinate backups: `config/backup.php:30-32,48,93-95` includes database files but does not follow links and explicitly dumps only `sqlite`; sharing the directory without revisiting backups may omit tenant data.

### R12 — Tenancy selection and authorization need separate lifecycle stages

**Evidence:** tenancy middleware is prepended before the normal web middleware (`bootstrap/app.php:23-25`), but calls `$request->user()` and checks username ownership at `ConnectToUserDatabase.php:49-52`. Cookies/session are initialized later in the standard web stack. Existing tests install a custom user resolver, so they do not exercise browser-session ordering.

The username equality rule also conflicts with creation of multiple distinct usernames in an existing tenant (`CreateUserCommandTest.php:349-388`). There is no documented decision whether a tenant is one user or an organization containing multiple users.

**Impact:** ordinary session authorization may not execute as intended; moving it after session initialization without deciding membership would deny additional users. Shared session configurations introduce additional identity-isolation questions. No cross-tenant account takeover was reproduced.

**Fix:** first establish the tenant membership model. Keep database selection before database-backed sessions/auth resolution; enforce membership after authentication. Test real sessions, overlapping numeric user IDs in separate databases, cookies, and main/tenant host transitions. A hard-coded username comparison is not a substitute for this decision.

### R13 — Hide two-factor credential material from model serialization

**Evidence:** `app/Models/User.php:20-23` hides only password and remember token. Fortify's installed `TwoFactorAuthenticatable` trait does not add hidden attributes.

`toArray()`/`toJson()` therefore include `two_factor_secret` and `two_factor_recovery_codes` when loaded. Fortify stores these encrypted, so this is not a plaintext secret disclosure or demonstrated public endpoint leak. They should still be excluded from generic serialization and logs.

**Fix:** add both attributes to `$hidden`, with serialization assertions that also confirm the intended setup/recovery UI remains functional.

### R14 — Fresh-install logging selects an unavailable driver

**Evidence:** `.env.example:19` sets `LOG_STACK=single,bugsnag`; `config/logging.php:78-80` selects driver `bugsnag`. The Composer manifest/installed package list contains no Bugsnag integration, and the reviewed service providers do not register that driver.

Resolving the default stack can fall back to Laravel's emergency logger with an unsupported-driver error. This obscures expected logging behavior on a fresh install.

**Fix:** use the supported `single` stack by default and remove/configure the dormant channel intentionally. Adding a new error-reporting dependency requires user approval.

## Further code improvements

1. **Replace blanket mass-assignment unguarding:** `ModelServiceProvider.php:12` calls `Model::unguard()` globally. Reviewed admin/invitation endpoints use explicit or validated fields, so no direct privilege escalation was identified. Model allowlists and explicit trusted assignments reduce future exposure; make this a separate, test-covered change.
2. **Bound list queries:** `UserController.php:17` and `InvitationController.php:18` load all matching records. Add stable ordering and pagination with corresponding view controls.
3. **Make tenant filesystem operations fail explicitly:** `TenantDatabaseService.php:73-79` uses a check-then-copy flow and ignores the copy result. Prefer exclusive creation, controlled errors, directory readiness checks, and tests in an isolated filesystem. Existing memory-database shortcuts skip the production behavior entirely.
4. **Make delivery failure recoverable:** `InvitationController.php:25-27` stores an active invite before sending mail. SMTP failure leaves a row that blocks retry. Add a deliberate resend path or delivery state; do not blindly queue tenant models without tenant-aware workers.
5. **Consolidate Composer test script:** `composer.json:93-96,103` declares `test` twice; the latter overrides the config-clearing version. Decide whether config clearing is desired and keep one definition.
6. **Align PHP support declarations:** `composer.json:14` advertises PHP `^8.2`, while the current installed stack targets a newer runtime. Choose and document the actual supported minimum across Composer and deployment. Current platform checks passed; this is not a claim that today's runtime is broken.
7. **Centralize administrative authorization:** repeated target-role checks in `UserController` and `BlockedUserController` can become policies while preserving existing behavior. Add missing return types to settings controller methods during related changes.
8. **Use consistent user-facing validation:** decide whether an administrator changing a verified user's email should clear verification (`UserController.php:33` does not; self-service `Settings/ProfileController.php:29-31` does). This is a policy question, not automatically an exploit.
9. **Broaden integration coverage where mocks conceal behavior:** command email tests should inspect generated URLs, tenant tests should exercise real isolated SQLite files and session middleware, and the invitation spoofing test should actually submit hostile email/role fields (`AcceptInvitationControllerTest.php:188-204` currently does not).

## Useful references

Version-specific Laravel documentation was consulted through Boost:

- https://laravel.com/docs/13.x/requests#configuring-trusted-hosts
- https://laravel.com/docs/13.x/passwords#configuring-trusted-hosts
- https://laravel.com/docs/13.x/database#database-transactions
- https://laravel.com/docs/13.x/fortify#two-factor-authentication

Use the companion plan for implementation order and acceptance tests. Recheck source line numbers before editing; they describe the reviewed revision.
