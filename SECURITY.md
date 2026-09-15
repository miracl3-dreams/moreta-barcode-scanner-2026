# Security Overview — moreta-barcode-scanner-2026

Handover document for third-party penetration testing. Describes the
architecture, the authentication and authorization model, the endpoint
inventory, and the risks that are known and accepted.

Last reviewed: 2026-09-04.

## 1. Architecture

| Layer | Technology |
| --- | --- |
| Frontend | React 19 + Vite SPA (dev port 5175), `@zxing` for camera scanning |
| Backend | Laravel 10 (PHP 8.1) JSON API under `/api` (dev port 8003) |
| Auth | Laravel Sanctum, **stateful session cookies** (SPA mode) |
| Session store | `database` driver on the `lstv_dbsystem` connection, encrypted at rest |
| Database | MySQL, shared with `moreta-2026` and the legacy `webmoreta` app |

A small **mobile-first** app used at the gate. Two functions:

1. **Barcode scanning** — scan a container/document barcode and record it.
2. **EIR signing** — show pending Equipment Interchange Receipts and capture
   the shipper representative's / driver's signature as an image.

It replaces the legacy `barcode_scanner` PHP app, which had a single page
(`view_barcode_scanner.php`) and no authorization checks at all. EIR signing is
new here and is derived from the `webmoreta` EIR module.

Because it runs on phones, the camera requires a secure context: HTTPS, or a
Chrome insecure-origin exception for a LAN IP.

**This app is not network-restricted.** There is no `lan` middleware on the
login route.

## 2. Authentication

`POST /api/login` authenticates against the legacy `userfile` table, matching
on **`usrname`** (not `usrcde`, unlike `moreta-2026`). Password verification
uses `App\Support\UserPassword::matches()`:

1. If `userfile.pwd_hash` is set, verify with bcrypt.
2. Otherwise compare `htmlentities($plain)` against `usrpwd` — a **plaintext
   comparison**. See section 5.

Note the password is `trim()`ed before comparison, so leading and trailing
whitespace is not significant.

Every successful login calls `User::upgradePasswordHash()`, which populates
`pwd_hash` with a bcrypt hash. Plaintext usage therefore shrinks as users log
in, but accounts that never log in here keep their plaintext `usrpwd`, and the
legacy apps still write that column.

There is no single-session lock and no `force-login` endpoint here.

## 3. Endpoint inventory and authorization

14 registered routes; 11 under `api/*` or `sanctum/*`. **3 are public**, and
every other endpoint requires an authenticated session:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/sanctum/csrf-cookie` | Issues the `XSRF-TOKEN` cookie |
| GET | `/api/login-context` | App title and caller IP for the login screen |
| POST | `/api/login` | Authenticate |

Authenticated endpoints:

| Method | Path | Gate |
| --- | --- | --- |
| POST | `/api/logout` | session |
| GET | `/api/user` | session |
| GET | `/api/barcode-scanner/lookups` | session only |
| POST | `/api/barcode-scanner/scan` | session only |
| POST | `/api/barcode-scanner/save` | session only |
| GET | `/api/eir-signing` | `menu:view_equip_interchange_receipt.php,view` |
| GET | `/api/eir-signing/{eir_form}` | `menu:view_equip_interchange_receipt.php,view` |
| POST | `/api/eir-signing/{eir_form}/signature` | `menu:view_equip_interchange_receipt.php,view` |

### EIR signing authorization

EIR signing is gated on the caller holding the `view_equip_interchange_receipt.php`
menu in `user_menus`, matching how `webmoreta` gates that module. In production
data 10 accounts hold that menu, plus 29 supervisors who bypass menu checks —
so roughly 39 of 146 accounts can sign, rather than all 146 as before.

`EnforceMenuPermission` was already present in this codebase but the `menu`
middleware alias was never registered in `app/Http/Kernel.php`, so no route was
actually permission-gated. The alias is now registered.

**Barcode scanning is intentionally left open** to any authenticated user. The
legacy scanner had no permission check for it, and no `menu:` program key for
barcode scanning exists in `user_menus` — gating it would lock out every
non-supervisor.

### There is no per-user ownership model

Worth stating plainly, because it looks like an IDOR at first glance. The
pending-EIR working set is **company-wide by design**: any gate operator signs
whatever container arrives. `EirSigningService::show()` and `store()` both call
`assertPending()`, which rejects an EIR that is already accepted or already
signed. That is the same filter `index` applies, so a caller cannot reach any
record through `/api/eir-signing/{recid}` that the list endpoint would not
already show them. There is deliberately no per-branch or per-user scope.

The authorization boundary here is therefore "holds the EIR menu", not "owns
this EIR". If per-branch separation is a business requirement, it does not
exist today and would need to be added.

## 4. Controls in place

- **CSRF**: `VerifyCsrfToken` with an empty exception list. The SPA sends
  `X-XSRF-TOKEN`.
- **Rate limiting**: `login` at 5/min keyed on IP + submitted `usrname`;
  general `api` limiter at 60/min.
- **Security headers**: `SecurityHeaders` middleware on the global stack —
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
  `Referrer-Policy: same-origin`, `X-Permitted-Cross-Domain-Policies: none`,
  and HSTS when the request is HTTPS. `Permissions-Policy` here is
  `camera=(self), microphone=(), geolocation=()` — camera stays allowed because
  scanning depends on it.
- **Session**: encrypted (`config/session.php` → `'encrypt' => true`),
  regenerated on login. `SESSION_SECURE_COOKIE` is env-driven.
- **CORS**: explicit origin list, no wildcard. Two dev-only wideners, both
  requiring `APP_ENV=local` **and** `ALLOW_DEV_ORIGINS=true`:
  the `AppServiceProvider` tunnel/LAN widening, and the
  `allowed_origins_patterns` entry in `config/cors.php`. That pattern is now
  restricted to loopback and RFC1918 ranges; it previously matched **any**
  IPv4 address.
- **Boot guard**: the app refuses to boot when `APP_ENV=production` and
  `APP_DEBUG=true`.
- **Uploads**: signature images are validated, checked for a transparent
  background, and stored under a **server-generated** filename with a fixed
  `.png` extension — the client-supplied filename is never used. Files live
  outside the web root (`storage/app/eir-uploads`, or `EIR_UPLOADS_PATH`).
- **SQL**: Eloquent and the query builder; parameters are bound.
- No `exec`, `eval`, `system` or `unserialize` anywhere in application code.
- No public landing page: `/` is unrouted and returns 404.

### Shared upload folder

`EIR_UPLOADS_PATH` is normally pointed at the **same directory** as
`moreta-2026/backend/storage/app/eir-uploads`, so a signature captured on a
phone can be printed from the desktop app. Confirm during testing that this
directory is not directly web-served by either app, and that the account
running PHP cannot write outside it.

## 5. Accepted risk — legacy plaintext passwords

**Status: known, accepted, not fixed. Expect any tester to report this as
Critical.**

`UserPassword::matches()` falls back to a plaintext comparison against
`userfile.usrpwd` when `pwd_hash` is empty. This is required for
interoperability with the legacy `webmoreta` app, which reads and writes that
column directly and is still in production use.

Compensating controls:

- bcrypt (`pwd_hash`) takes precedence whenever it is populated, and **every**
  successful login here upgrades the account.
- Login is rate limited to 5 attempts/min per IP + username.
- `usrpwd` and `pwd_hash` are in the model's `$hidden` array and are not
  returned by any API response.

Residual risk: no network gating on this app, so the login endpoint is exposed
wherever it is deployed. Anyone with read access to the database recovers
usable credentials for accounts that have not yet been upgraded.

## 6. Other known items

- **Supervisor bypass**: `usrlvl = 'supervisor'` skips menu permission checks,
  so supervisors can sign EIRs without holding the EIR menu. Intended.
- **Large inherited surface.** This app is a fork of the main ERP and ships
  roughly 48 controllers (plus matching services and repositories) that **no
  route points to**. They are not reachable at runtime, but they are one
  routing mistake away from exposure. A decision on deleting them was deferred;
  confirm during testing that none are routable.
- **Shared database**: schema changes are additive only. Never `DROP TABLE`,
  `DROP COLUMN` or `ALTER ... DROP`.
- **Dependencies**: pinned to Laravel 10 / PHP 8.1. Report findings rather than
  upgrading.

## 7. Test accounts

Do not test with real staff credentials. Provision through the User File module
in `moreta-2026` (`mf_userfile.php`):

1. A **non-supervisor** account **without** the
   `view_equip_interchange_receipt.php` menu — must be able to scan barcodes
   but must receive 403 on every `/api/eir-signing/*` endpoint.
2. A **non-supervisor** account **with** that menu — must be able to list,
   view and sign pending EIRs.
3. A **supervisor** account, to establish the intended-maximum baseline.

Remember accounts here are looked up by `usrname`, not `usrcde`.

## 8. Pre-test deployment checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `APP_KEY` generated, and distinct from the other two apps
- [ ] **HTTPS is required** for camera access on mobile browsers, and for the
      session cookie. Then set `SESSION_SECURE_COOKIE=true`.
- [ ] Dedicated MySQL user with least privilege — **not** `root` — and a strong
      password. `DB_PASSWORD` must not be empty.
- [ ] `ALLOW_DEV_ORIGINS` unset or `false`
- [ ] `SANCTUM_STATEFUL_DOMAINS` and `FRONTEND_URL` set to real hostnames only;
      remove any LAN IPs used during development
- [ ] `EIR_UPLOADS_PATH` points at the shared folder and is **not** web-served
- [ ] `php artisan config:cache route:cache` after configuration is final
- [ ] Frontend served as a built bundle (`npm run build`), not the Vite dev
      server
- [ ] Confirm `/` returns 404 and no stack traces are reachable

## 9. Rules of engagement

- The legacy apps at `webmoreta` and `barcode_scanner` are **out of scope** and
  must not be modified.
- The MySQL database is shared and live. Destructive testing (`DROP`,
  `TRUNCATE`, `ALTER ... DROP`) is prohibited.
- Signing an EIR mutates live operational data. Coordinate before testing the
  signature `POST`, or use a restored database copy.
