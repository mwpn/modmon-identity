# modmon-identity

Portable Identity/Auth platform module for [ModMon Foundation](https://github.com/mwpn/modmon-foundation)
hosts. Owns the user/auth domain (`users`, `password_reset_tokens`), runtime
Laravel auth wiring, and session authentication flows per ADR-0006 Strategy D.

**Status:** v1.0.0 — certified portable ([compliance report](docs/reports/identity-compliance-v1.md)).

This repository contains **only** the Identity module. It is not a standalone
Laravel application. Install it into a compatible Foundation host.

## Requirements

| Requirement | Constraint |
|-------------|------------|
| PHP         | `^8.3`     |
| Laravel     | `^13.0`    |
| Foundation  | `^1.0`     |

Foundation must include accepted runtime fixes for portable modules (migration
`--realpath`, post-boot route name lookup refresh, provider re-register on
enable). Foundation `main` at or after the Identity v1 extraction satisfies
this.

## Install from Git

```bash
# 1. In your ModMon Foundation host project root
git clone https://github.com/mwpn/modmon-identity.git /tmp/modmon-identity
cp -r /tmp/modmon-identity/Modules/Identity ./Modules/Identity

# 2. Verify and install (no host source edits required)
php artisan module:doctor identity
php artisan module:install identity

# 3. Bootstrap a user (optional)
php artisan identity:user:create \
  --name="Site Owner" \
  --email=owner@example.com \
  --password="your-secure-password"
```

On Windows (PowerShell):

```powershell
git clone https://github.com/mwpn/modmon-identity.git $env:TEMP\modmon-identity
Copy-Item -Recurse $env:TEMP\modmon-identity\Modules\Identity .\Modules\Identity
php artisan module:doctor identity
php artisan module:install identity
```

No changes to `bootstrap/app.php`, `routes/web.php`, `config/auth.php`, or
`.env` are required.

## Module documentation

Canonical module README (capabilities, routes, contracts, disable/enable):

[`Modules/Identity/README.md`](Modules/Identity/README.md)

## Testing (inside a Foundation host)

After copying the module into a host, register the test directory in the host's
`phpunit.xml` (if not already present):

```xml
<testsuite name="Modules">
    <directory>Modules/Identity/Tests</directory>
</testsuite>
```

Then run:

```bash
php artisan test --filter="Modules\\Identity"
```

## Repository layout

```
modmon-identity/
├── Modules/Identity/          # Portable module (copy this folder into host)
├── docs/reports/              # Compliance and certification artifacts
└── README.md                  # This file
```

## License

Proprietary — same terms as ModMon Foundation.
