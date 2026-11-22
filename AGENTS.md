# Restaurant POS SaaS V2

Use the existing checkout. The cloud task workspace is isolated. Create a worktree only when requested.

Read `docs/finalized-v2.md` for product scope and `docs/phase-0-technical-spec.md` for authorization and schema invariants. Follow the development commands in `README.md`. Use the locked Composer dependencies, `composer test`, and `vendor/bin/pint --test` before committing.

- Use Laravel controllers, Eloquent, Blade, and relational migrations. Keep secrets, local databases, logs, and vendor dependencies out of Git.
- Staff sign in with the restaurant email, individual staff email, and password. Keep central administrator identities and routes behind the separate central guard.
- Derive restaurant scope from authenticated staff. Resolve any staff-facing branch through the active assignments of that member. Scope central child lookups to the restaurant in the route.
- Use composite foreign keys for relationships that must share restaurant ownership. Use transactions for changes spanning multiple records. Add isolation and revocation tests when changing authorization.
- Browser operations require a live server round trip. Keep operational responses non-cacheable. Do not introduce a service worker, offline queue, browser data store, or synchronization workflow.
- Implement the phase requested. Record decisions and open product requirements in the specifications. Follow the user's direction for commits and remote repository changes.
