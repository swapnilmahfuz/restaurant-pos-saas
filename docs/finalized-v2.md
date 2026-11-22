# Restaurant POS SaaS — Finalized V2

## Confirmed requirements

- The system supports multiple independent restaurants and multiple branches per restaurant.
- Staff sign in using their restaurant's email as the restaurant identifier. Each staff member uses an individual email and password for authentication.
- Central administrators manage restaurants, branches, staff accounts, and branch assignments from one browser workspace.
- Staff access is limited to their restaurant and their assigned active branches. Deactivation takes effect on the next protected request.
- All browser operations require the server. The application does not store business data for offline use, queue writes in the browser, or synchronize offline changes.

## Phase 0 scope

Phase 0 delivers the Laravel project, migrations, separate sign-in flows, branch and restaurant workspaces, central provisioning, account and branch activation, and automated security regression tests. Restaurant email is globally unique. Staff email is unique within a restaurant, so the same person can hold distinct memberships at different restaurants.

POS workflows, catalog, inventory, payments, reporting, subscriptions, and expanded roles require product specifications before implementation. Phase 0 does not establish workflows for password recovery, administrator MFA, or a business audit trail.
