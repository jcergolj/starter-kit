# Tenant Session Isolation

Issue #3 characterizes the current tenant session behavior before membership enforcement is designed.

## Current observation

The feature test uses overlapping user IDs and confirms that a session established on one tenant host is accepted on another tenant host. This is a characterization of the current isolation gap, not an endorsement of that behavior or of the username-equals-subdomain check as the long-term membership policy.

## Decision required

Issue #21 must define whether tenants represent single users or shared organizations. After that decision, membership enforcement should run after session initialization while retaining tenant database selection before database-backed authentication and session access.
