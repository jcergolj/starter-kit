# Remediation plan for an implementation agent

Read [the review](code-review.md) first. This plan fixes the findings there; it is not evidence that they have already been fixed.

## Working rules

1. Read repository/framework instructions and check `git status` before editing. Preserve existing work.
2. Confirm installed PHP/Laravel/Fortify/PHPUnit versions; the review observed 8.4.25 / 13.26.1 / 1.38.0 / 13.3.1. Consult Boost documentation for those versions before changing Laravel behavior.
3. **Complete task 1 before running filesystem tests or the entire suite.** They currently delete application database files.
4. For each task: read the listed source/tests; add a regression test; run it and confirm the expected failure; implement the smallest fix; rerun that file and directly affected tests. Do not delete failing tests or assert only implementation details.
5. Use existing test directories, factories, PHPUnit attributes, named routes, Form Requests, translations, and project conventions. Generate new PHP classes/tests with the appropriate Artisan make command after checking help.
6. Do not update dependencies, migrate live tenant files, send real invitations, or execute deployment scripts to test a fix. Use temporary roots, SQLite fixtures, fake mail, and disposable deployment/build environments.
7. The review ran three existing files successfully with `vendor/bin/phpunit <paths>`. Prefer `php artisan test --compact <path>` where compatible; use direct PHPUnit if Artisan forwards unsupported flags. Do not pass `--no-interaction` to PHPUnit. Other Artisan commands should use `--no-interaction` as instructed by the project.
8. Keep each task independently reviewable. Record tests run and remaining blockers. Do not bundle optional refactors into security fixes.

## Order and dependencies

Recommended order: **1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12**.

- Task 4 contains a required product decision; finish its characterization tests and ask the owner before implementing a membership model.
- Tasks 4 and 5 depend on task 1's filesystem isolation.
- Tasks 6 and 7 must use the same invitation lifecycle/claim rules.
- Tasks 8–12 can proceed if the tenant membership decision is blocked.
- Optional improvements come after the confirmed bugs and relevant regression tests.

## 1. Isolate filesystem tests before anything else (R01)

**Edit:** `tests/Feature/Commands/CreateUserCommandTest.php`, `tests/Unit/Services/TenantDatabaseServiceTest.php`, `tests/Unit/Middleware/ConnectToUserDatabaseTest.php`; introduce a shared test helper only if needed within the existing tests structure.

Steps:

1. Inspect every direct filesystem call in these files. Remove the behavior that enumerates and deletes arbitrary application tenant databases, not the tests themselves.
2. Use a unique temporary root per test/parallel worker. Set the application's database path to that root using the installed Application API (verify its setter first). Save the original path for restoration.
3. Create `db/`, a template database where needed, and only the fixture files required by the test. No fixed fixture names in the real `database/db` directory.
4. In cleanup, restore the application path and disconnect temporary SQLite connections; remove only that test's root. Assert the cleanup target is inside the temporary parent.
5. Add a sentinel fixture outside the owned test root, also in temporary storage. Verify setup/cleanup preserves its contents. Never use real tenant data as the sentinel.
6. Replace `returns_404_when_database_connection_fails`'s `assertTrue(true)` with a real missing-file/race response test or accurately named meaningful test.

**Done when:** these three files can run repeatedly and in parallel without touching real database files; sentinel survives; all existing behavioral assertions still run.

**Verify:** run each changed file with `vendor/bin/phpunit <path>`, then the three together. Only after this passes consider broader tests.

## 2. Restrict trusted hosts and tenant hostname parsing (R03)

**Edit:** `bootstrap/app.php`, `app/Services/TenantDatabaseService.php`, `tests/Unit/Services/TenantDatabaseServiceTest.php`, relevant middleware/auth feature tests.

Add regression cases:

- Configured base host is accepted without tenant selection.
- `acme.<base>` selects `acme`; a valid but nonexistent tenant returns 404.
- `acme.attacker.test`, `acme.<base>.attacker.test`, and unsupported nested labels are rejected before opening a tenant database.
- Single-database mode also rejects untrusted hosts.
- Supported local development hosts work under explicitly configured test/development domains.

Implementation:

1. Parse the host relative to `config('app.domain')`, not merely its first label. Accept exactly one tenant label unless the owner explicitly requires nested tenants.
2. Configure Laravel's `trustHosts` with escaped/anchored domain patterns. Verify middleware behavior in tests: local/testing environments can bypass trust-host checks unless tests deliberately enable the production-equivalent path.
3. Add a full request test with fake password-reset notifications proving an untrusted host cannot produce an attacker-controlled reset URL. Use fake mail/notifications and isolated users, not a real address.
4. Do not conflate filesystem-safe subdomain syntax with DNS label validity. Cover invalid labels and maximum supported length.

**Done when:** host validation occurs before database selection and URL generation; both single/tenant mode tests pass.

## 3. Characterize and fix tenant authentication ordering (R12)

**Read/edit:** `bootstrap/app.php`, `ConnectToUserDatabase.php`, `TenantDatabaseService.php`, relevant session configuration and middleware tests.

**Required owner decision:** is a tenant restricted to a single username, or can multiple users in that tenant database sign in? Current CLI/UI flows support multiple users, while the middleware compares username to subdomain. Ask this exact question and record the answer; do not invent a tenant schema or silently remove access control.

Before changing behavior:

1. Using task 1's isolated real SQLite files, create tenant A and B with overlapping numeric user IDs and distinct credentials. Migrate fixture databases using the actual migrations.
2. Exercise actual login and subsequent requests with persisted cookies/sessions. Do not use only `actingAs()` or custom user resolvers for these tests.
3. Test correct-tenant login, wrong-tenant credentials, tenant A cookies sent to B, root-domain transitions, remember-me, and the configured session driver. Confirm the authenticated identity and active database on every request.
4. Demonstrate what the existing username check does with real middleware ordering. Document actual failures without claiming an exploit the tests do not prove.

After the decision:

5. Keep tenant resolution/database switching before database-backed session initialization. Put authenticated membership enforcement after session/auth initialization.
6. Implement the approved membership rule. For database-local users, prove that a session from a different database cannot identify a user merely by colliding ID; do not assume host-only cookies alone prove server-side isolation.
7. Ensure a later main-domain operation does not retain stale tenant context in any supported persistent process lifecycle. Restore context on error paths where appropriate.

**Done when:** real-session isolation tests pass and legitimate users under the chosen model remain usable. If the design decision is missing, leave this task explicitly blocked.

## 4. Preserve tenant data across deployment and verify backups (R11)

**Edit:** `deploy.php`, `config/backup.php`; add tenant migration/provisioning commands only with a clearly defined tenant-mode deployment contract.

Steps:

1. Add tenant data storage to Deployer's shared directories. Make it writable by the appropriate application user. Do not relocate existing live files automatically in code review work.
2. Verify an old and a new disposable release resolve to the same tenant database files, even after old-release cleanup.
3. Define how all existing tenant databases receive schema changes and how a current, schema-only template is produced for new tenants. Use forward migrations; do not run `migrate:fresh` on persistent files.
4. Review backup symlink traversal and SQLite snapshot consistency. The current explicit dump list only includes `sqlite`; raw copies of actively written tenant files are not a substitute for tested consistent snapshots.
5. Restore a disposable backup and assert known records exist in both the main database and multiple tenant databases. Check integrity after restoration.

**Done when:** release rotation preserves tenant data, schema updates reach fixtures, and restore verification covers shared tenant paths. Record operational steps needed for an existing deployment rather than running them.

## 5. Carry tenant identity into invitation URLs (R05)

**Edit:** `CreateUserCommand.php`, `InvitationMail.php`, `SubdomainUrlBuilder.php` if needed, `CreateUserCommandTest.php`; add mailable tests under `tests/Unit`.

Steps:

1. Add tests inspecting the mailable's rendered acceptance link for current database, new tenant, and existing tenant selections. Mail fakes alone are insufficient unless the callback inspects the URL.
2. Track selected tenant explicitly, including existing tenants, rather than only `$newTenantSubdomain`.
3. Pass nullable tenant context or a prebuilt trusted acceptance URL into the mailable. Generate the path from the named route and combine it with a trusted configured host; retain scheme and port.
4. Add an HTTP invitation test to ensure web-generated links stay in the current validated tenant context.
5. Verify the link reads the database containing its token. Do not infer tenancy from an arbitrary user-controlled hostname or mutable global state.

**Done when:** all three CLI paths generate correct links and a tenant link resolves its own invitation.

## 6. Repair invitation reissue behavior (R04)

**Edit:** `SendInvitationRequest.php`, `Invitation.php`, `InvitationController.php` as needed; request, model, and controller tests.

Recommended contract: **one row per email**, preserving the existing unique index. Reissue inactive invitations by rotating their token, expiry, role, language, and clearing `accepted_at`; reject an active invitation or an existing user. This avoids an unnecessary schema change. If historical invitation rows are a product requirement, ask before choosing a different schema.

Add tests first:

1. Expired invitation can be reissued through the HTTP endpoint and produces one row/new token; its old token cannot be accepted.
2. Accepted invitation for a deleted user can be reissued without a unique constraint error.
3. Existing user still blocks reissue.
4. Unexpired, unaccepted invitation still blocks duplicate sending.
5. Expiry exactly equal to current time is inactive; freeze time.
6. CLI path uses the same lifecycle rules.

Implementation steps:

7. Align the validation pending predicate with `Invitation::pending()` including expiry.
8. Put create/reissue logic in one model method/action used by HTTP and CLI. Serialize competing reissues appropriately; preserve unique email/token constraints and handle races deliberately.
9. Keep role/language trusted: HTTP currently creates regular-user invitations; posted `role=superadmin` must not alter that.

**Done when:** all lifecycle cases work at controller level, not just Form Request level. Run request/model/controller files affected.

## 7. Make acceptance atomic and predictable (R06)

**Edit:** `AcceptInvitationController.php`, `Invitation.php` or a small action if justified, `AcceptInvitationControllerTest.php`.

Add tests:

- Valid token creates exactly one user and consumes the invitation.
- Duplicate/replayed token never creates another user and returns the agreed invalid-invitation response.
- A failure after the invitation claim rolls back both claim and user insert.
- An email that became registered after issuance returns a controlled response, not 500.
- Concurrent acceptance against a temporary file-backed SQLite database has one winner and a controlled loser. Do not mistake sequential requests or in-memory fixtures for a concurrency test.
- Posted email/role cannot override the invitation; actually include spoofed fields in the request.

Implementation:

1. Use a transaction on the active tenant/main connection.
2. Inside it, atomically claim the row using a conditional update constrained by token, null acceptance, and future expiry. Check the affected-row count. If zero, return the established invalid-token outcome.
3. Create the user inside the same transaction so an insert failure rolls back the claim. Preserve unique constraints.
4. Handle expected conflicts at the correct boundary after rollback. Do not catch every database error and return success. SQLite does not provide `SELECT ... FOR UPDATE` row locking; validate the actual strategy with the concurrent test.

**Done when:** no half-completed acceptance and no unhandled expected duplicate conflict remains.

## 8. Normalize email on every supported write path (R07)

**Edit:** invitation/admin Form Requests, CLI input handling, shared persistence path where appropriate; relevant login/request/command tests.

Steps:

1. Add end-to-end tests: invite mixed-case email, accept, then log in successfully with upper/lowercase input on SQLite.
2. Test admin email edits and direct CLI creation. Test whitespace trimming and case-variant duplicate rejection for invitations/users.
3. Normalize before uniqueness validation and persistence. Reuse one normalization policy; remember the request `username` field is not the email login key.
4. Review legacy data separately. Provide a read-only collision report before planning a backfill; stop for a human decision on conflicting identities. Do not automatically merge accounts or delete rows.

**Done when:** every new/updated email follows the login normalization policy; legacy migration requirements are explicit.

## 9. Harden two-factor setup state, validation, and serialization (R08, R13)

**Edit:** `Settings/RecoveryCodesController.php`, `Settings/ConfirmedTwoFactorController.php`, `Settings/ConfirmTwoFactorRequest.php`, `User.php`; matching controller/model/request tests.

Steps:

1. Test a password-confirmed user with no 2FA secret visiting setup/recovery URLs. Decide on a consistent redirect to the two-factor settings page for absent setup data; assert no 500.
2. Test setup-pending and fully confirmed states. Only show secrets/codes in the intended state. Keep password confirmation middleware active in security tests; add explicit tests for unconfirmed passwords rather than disabling it everywhere.
3. Require a string matching exactly six ASCII digits. Test valid `012345`, too short/long, nondigits, integers if the endpoint expects a string, and arrays. Invalid input must fail validation before reaching the provider.
4. Add secret/recovery-code names to User `$hidden`. Assert they are absent from `toArray()` and decoded `toJson()` while password and remember token remain hidden.
5. Keep using Fortify's encryption conventions; do not double-encrypt existing columns by adding unrelated encrypted casts.

**Done when:** both state transitions and malformed requests are handled intentionally; generic serialization excludes credential material; existing 2FA flow still works.

## 10. Correct OTP input synchronization (R09)

**Edit:** `resources/js/controllers/otp_controller.js`, possibly `resources/views/components/form/otp-input.blade.php`.

Steps:

1. Use the existing browser tooling or an approved frontend test harness. Do not add a JS dependency just for this fix without approval.
2. Reproduce by setting each visible digit and dispatching only `input` events; assert the hidden `code` equals the visible six digits.
3. Synchronize the hidden field immediately after every mutation: input, keydown, paste, backspace, clear, and initial connection. Remove delayed synchronization that permits stale immediate submission.
4. Test normal typing followed immediately by submit; leading zeroes; mobile-style input without digit keydown; clearing; and mixed/short pasted content replacing existing digits.
5. Make the paste behavior deterministic so stale digits from a previous code are not silently retained.

**Done when:** submitted value always matches displayed digits across these interactions; include the browser/test result in the handoff.

## 11. Permit blocked users to log out (R10)

**Edit:** `EnsureUserIsNotBlocked.php`, middleware and authentication controller tests.

Steps:

1. Add a regression test that authenticates a user, blocks them, then submits the named logout route. Assert redirect plus `assertGuest()` and session invalidation.
2. Retain a test that the same blocked user gets 403 on dashboard/admin/settings routes.
3. Exempt only the named logout route from the blocked-user denial, or use another equally narrow design. Preserve the web/CSRF middleware.
4. Verify normal-user logout remains unchanged. Separately ask whether blocked credentials should be rejected at login; do not conflate that with the logout fix.

**Done when:** blocked users can end their session but cannot access protected actions.

## 12. Fix default logging and small configuration inconsistencies (R14)

**Edit:** `.env.example`, `config/logging.php`, `composer.json` for its duplicate test script; targeted provider/config tests.

Steps:

1. Reproduce default stack resolution with the example configuration in a test using temporary log storage.
2. Set the default example stack to supported drivers. Remove the unused Bugsnag channel if no integration is intended; do not install a new dependency implicitly.
3. Assert the selected logger resolves without an emergency unsupported-driver fallback.
4. Choose one `scripts.test` definition, retaining or deliberately omitting config clearing. Run `composer validate --no-check-publish` and confirm the duplicate-key warning disappears.

**Done when:** fresh-install logging is functional and the manifest has one deliberate test command.

## Optional follow-up tickets

These are improvements, not prerequisites to declaring the individual fixes complete:

- **Mass-assignment allowlists:** inventory writes, add model allowlists/trusted explicit assignments, then remove global unguarding. Test that role/blocked/verification fields cannot be changed through unauthorized request payloads, while CLI/admin trusted paths still work.
- **Pagination:** add stable ordering and pagination to users and pending invitations; test multiple pages and rendered controls. Preserve role filtering.
- **Provisioning robustness:** validate supported DNS labels, use exclusive file creation, handle missing template/unwritable destination explicitly, and build a schema-only current template. Verify repeated/concurrent creation cannot overwrite an existing tenant. Remove production behavior shortcuts once isolated integration tests replace them.
- **Recoverable mail delivery:** define resend semantics after mail failure. If queueing later, serialize tenant identity explicitly and restore tenant context in workers before model hydration; add tenant-isolation tests first.
- **PHP support:** agree on a minimum runtime and align the Composer root constraint and deployment instructions. Current lock/platform validation passed; avoid unnecessary upgrades.
- **Admin policies/types:** extract repeated authorization only if it improves clarity; preserve the prohibition on editing/deleting/blocking admin targets. Add explicit controller return types while touching those methods.
- **Email verification policy:** decide how admin email changes affect verification and test that decision consistently with self-service updates.

## Final verification and handoff

1. Run each changed test file; report exact commands and counts.
2. Once task 1 is verified, request/run the full suite according to the user's authorization. Do not use the previous 26 passing tests as evidence of complete coverage.
3. Re-run dependency audit only if dependencies changed; re-run manifest validation if Composer files changed.
4. Review `git diff --check` and `git diff`; ensure no runtime databases, secrets, generated artifacts, or temporary fixtures are included.
5. For each review ID, report **fixed and verified**, **implemented but verification blocked**, **decision needed**, or **deferred**. Include remaining deployment/legacy-data work explicitly.
6. Do not claim a production deployment, backup restore, or exploit test passed unless it actually ran in the intended disposable environment.
