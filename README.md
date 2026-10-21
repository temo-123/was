# Climbing Gym — management system

Laravel 13 + Vue 3 + Bootstrap 5 (AdminLTE 4 admin panel), MySQL.

## Features
- Email/password auth (register, login, forgot/reset password, email verification) + **Google OAuth**.
- Roles & permissions (admin, worker, user) with per-user extra permissions.
- **Entrance screen** (`/admin/entry`): search visitor by name / ID / phone → register entry,
  or create a new visitor in the same form. The price is calculated automatically from the visitor's
  age and the current time; active subscriptions are used automatically.
- Visitors, visits (filters, totals, CSV export), subscriptions, editable **price matrix**,
  **price coefficients** (global, per group/time/ticket/category, weekdays, date ranges),
  age groups / time slots / ticket types / categories editor.
- Automatic link between a registered user and an existing visitor (same ID number or verified email),
  manual link/unlink in the admin panel.
- Users, roles, permissions overview, activity log, profile page, terms of use, privacy policy.
- REST API `/api/v1/*` (Sanctum tokens for a future app, session cookie for the admin panel).

## Install
```bash
composer install
npm install
cp .env.example .env && php artisan key:generate   # skip if .env exists
# set DB_*, ADMIN_EMAIL, ADMIN_PASSWORD, GOOGLE_* in .env
php artisan migrate --seed          # tables + permissions + price list + first super admin
php artisan storage:link
npm run dev                         # or: npm run build
```

Create more staff accounts: `php artisan app:create-admin someone@example.com --role=worker`.

### MySQL
```sql
CREATE DATABASE climbing_gym CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE climbing_gym_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'climbing'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON climbing_gym.* TO 'climbing'@'localhost';
GRANT ALL PRIVILEGES ON climbing_gym_test.* TO 'climbing'@'localhost';
FLUSH PRIVILEGES;
```

### Google sign-in
Google Cloud Console → APIs & Services → Credentials → OAuth client ID (Web application).
Authorized redirect URI: `http://climbing.loc/auth/google/callback` (your `APP_URL` + `/auth/google/callback`).
Put the client ID/secret into `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`. The Google button appears
automatically once the client ID is set.

## Prices
Final price = `price_rules` value × product of all active matching `price_coefficients`.
- Change a single price: **Admin → Price list** (empty cell = not sold).
- Raise/lower everything: edit the **Global** coefficient (e.g. `1.100` = +10%).
- Weekend / holiday / promo prices: add a coefficient with weekdays or a date range.

## API (v1)
| Method | URL | Permission |
|---|---|---|
| POST | `/api/v1/auth/login`, `/api/v1/auth/register` | — (returns token) |
| GET | `/api/v1/auth/me`, POST `/api/v1/auth/logout` | logged in |
| GET | `/api/v1/prices` | public |
| GET | `/api/v1/pricing/quote?visitor_id=&ticket_type_id=` (or `birth_date=`) | pricing.view |
| GET | `/api/v1/visitors?search=`, `/api/v1/visitors/search?q=` | visitors.view |
| POST/PUT/DELETE | `/api/v1/visitors/{id}` | visitors.create / update / delete |
| GET | `/api/v1/visits?from=&to=` | visits.view |
| POST | `/api/v1/visits` (`visitor_id` **or** `visitor{...}`, `ticket_type_id`, `payment_method`) | visits.create |
| DELETE | `/api/v1/visits/{id}` | visits.delete |
| GET | `/api/v1/me/visits`, `/api/v1/me/subscriptions` | logged in (own data) |

## Tests
```bash
php artisan test
```
