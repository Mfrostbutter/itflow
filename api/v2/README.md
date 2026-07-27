# ITFlow API v2 (fork)

A clean, additive REST API carried by this fork. Everything lives under
`api/v2/` as new files; stock v1 endpoints are untouched and behave
identically to upstream. Reverting to stock is a branch checkout.

## Detection

```
GET /api/v2/capabilities.php
```

Stock ITFlow returns 404 for this path; this fork returns:

```json
{ "success": true,
  "data": {
    "fork": true,
    "fork_api": "2.0.0",
    "upstream_base": "26.07.1 (master@698135d)",
    "features": ["tickets.read.filtered", "tickets.update", "tickets.assign",
                 "tickets.reply", "ticket_replies.read", "..."],
    "v1_extensions": ["tickets/update", "tickets/reply"] } }
```

Key off `features`, never off guesses. The list is computed per deployment:
extension-schema features (see below) appear only where their tables exist.

## Authentication

Standard ITFlow API keys (Admin > API). Preferred transport:

```
Authorization: Bearer <key>
```

`?api_key=<key>` is also accepted for v1 back-compat, but keys in query
strings land in web-server access logs; use the header.

Client-scoped keys are locked to their client. All-clients keys may pass
`client_id` to narrow reads and MUST pass it on writes.

## Contract

- `success` is a real boolean.
- Real HTTP status codes: 200 read/update, 201 create, 400 malformed request,
  401 auth, 403 scope, 404 not found / feature unavailable, 405 method,
  422 validation, 500 internal.
- Failures carry `error: { code, message, field? }` with a closed code set:
  `AUTH_INVALID`, `AUTH_EXPIRED`, `SCOPE_DENIED`, `NOT_FOUND`,
  `MALFORMED_JSON`, `VALIDATION_FAILED`, `METHOD_NOT_ALLOWED`,
  `FEATURE_UNAVAILABLE`, `INTERNAL`.
- `data` is always present on success (empty array/object when empty).
- POST bodies are JSON only; malformed or form-encoded bodies get a 400.
- Paginated reads return `meta: { count, page, per_page, total }`;
  `per_page` caps at 100.
- All SQL is prepared statements.

## Endpoints

### Tickets

```
GET  /api/v2/tickets/read.php
     ticket_id, client_id, status (name, case-insensitive), assigned_to,
     contact_id, created_after/created_before, updated_after/updated_before
     (YYYY-MM-DD, inclusive days), q (subject+details), sort
     (created_at|updated_at|priority), order (asc|desc), page, per_page
     Rows include ticket_status_name and ticket_assigned_to_name.

POST /api/v2/tickets/update.php
     { ticket_id, subject?, details?, status?, priority?, assigned_to?,
       contact_id?, asset_id?, billable?, vendor_ticket_number?, vendor_id? }
     Partial update from a strict allowlist; unknown fields 422. Status by
     name. vendor_ticket_number is varchar-safe ("ABC-123" round-trips).
     Cross-client contact/asset references 422. Returns the updated ticket.

POST /api/v2/tickets/assign.php
     { ticket_id, assigned_to }   (0 unassigns)
     Assignment flips status New -> Open, matching the agent UI. Returns the
     updated ticket.

POST /api/v2/tickets/reply.php
     { ticket_id, reply, type? (public|internal, default internal),
       time_worked? (HH:MM:SS), reply_by? (user id) }
     Side effects match the agent path: ticket touch, first-response stamp on
     the first Public reply, assigned-tech notification. Never emails the
     client. 201 with the created reply.

GET  /api/v2/ticket_replies/read.php
     ticket_id, type (public|internal), page, per_page
     Thread order, archived replies excluded, author name joined.
```

### Extension-schema features (conditional)

Some deployments extend ITFlow with `agreements` and `time_entries` tables.
These endpoints exist only there; elsewhere they return 404
`FEATURE_UNAVAILABLE` and are absent from `capabilities.features`.

```
GET  /api/v2/agreements/read.php
     client_id, status, active (bool), expiring_before (YYYY-MM-DD)

GET  /api/v2/time_entries/read.php
     client_id, ticket_id, tech_id, billable (bool), date_from, date_to

POST /api/v2/time_entries/create.php
     { hours, tech_id, client_id, ticket_id?, rate?, billable?, date?, note? }
     201 with the created entry.
```

## Versioning

`fork_api` is semver: minor bumps are additive. `upstream_base` names the
upstream release this fork is rebased on. Releases are tagged
`<upstream_release>+fork.N`.

## Testing

`tests/api-v2/` stands up a disposable ITFlow (docker compose), installs via
`scripts/setup_cli.php`, loads a fixture, and runs a zero-dependency probe
suite against every endpoint, including a v1 no-regression canary. CI runs it
on every push.

```
docker compose -f tests/api-v2/docker-compose.yml up -d --build --wait
docker compose -f tests/api-v2/docker-compose.yml exec -T app php scripts/setup_cli.php ...
docker compose -f tests/api-v2/docker-compose.yml exec -T db mariadb -uitflow -pitflowpass itflow < tests/api-v2/fixture.sql
node tests/api-v2/run_probes.mjs http://localhost:8087
```

## Relationship to upstream

Independent fork for our own deployments. Upstream ITFlow is not accepting
outside contributions; nothing here is submitted there. The fork is
new-files-only (plus one model fix), so rebasing onto upstream releases stays
near conflict-free and `git checkout master` restores stock behavior.
