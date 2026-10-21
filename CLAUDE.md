# Climbing gym management — project notes

Laravel 13 · PHP 8.3+ · MySQL 8 · Vue 3 + Bootstrap 5.3 (AdminLTE 4 in the admin panel) · Vite.

## Commands
- `php artisan migrate:fresh --seed` — rebuild DB with roles, prices, test accounts, ~50 visitors, ~500 visits
- `php artisan test` — feature tests (MySQL DB `climbing_gym_test`)
- `php artisan permissions:sync` — after editing `config/permissions.php`
- `npm run build` — assets for Apache (`public/build`); `npm run dev` for hot reload
- Apache serves `/var/www/html` (vhost climbing.loc) with mod php8.3 → Composer is pinned to `platform.php 8.3.0`;
  root `.htaccess` rewrites everything into `public/`. The site is also opened as `was.loc` from another machine;
  `config/sanctum.php` always treats the current request host as stateful so the Vue → /api calls work on any host.
- Visitor validation (server + client): ID number exactly 11 digits, phone 9–15 digits (spaces/dashes stripped by
  `VisitorRequest::normalize`), real email format. Blade forms get live validation via `data-validate` / `data-digits`
  (`resources/js/forms.js`).

## Roles & test accounts
Roles: `admin` (bypasses all checks), `worker` (reception staff), `user` (registered climber, default).
Seeded accounts, password `secret`: admin@test.loc, worker@test.loc, user@test.loc.
Seeders: RoleSeeder, PricingSeeder, UserSeeder, VisitorSeeder (Georgian names), VisitSeeder.

## Main ideas
- **Roles & permissions** are custom (no package): tables `roles`, `permissions`, `role_permission`,
  `user_role`, `user_permission`. Every permission name is a Gate (`@can('visitors.create')`,
  `->middleware('can:visitors.create')`) via `Gate::before` in `AppServiceProvider`.
  Add permissions in `config/permissions.php`, then run `php artisan permissions:sync`.
- **Visitors** (`visitors`) are gym cards created by staff. A registered **user** is linked to a
  visitor via `visitor_user` (1:1). Auto-linking: `App\Services\VisitorLinker` (by personal ID, or by
  verified email).
- **Visits** (`visitor_visits`) store a snapshot of the price calculation (age, group, slot, base price,
  coefficient, final price).
- **Pricing**: `price_rules` matrix (age group × time slot × ticket type × optional visitor category)
  multiplied by every matching `price_coefficients` row. Logic: `App\Services\Pricing\PriceCalculator`.
- **Entrance**: `App\Services\EntryService::register()` — used by `POST /api/v1/visits`, which the Vue
  component `resources/js/components/VisitorEntry.vue` calls with the session cookie (Sanctum stateful).
- Subscriptions (`visitor_subscriptions`) cover entries in their own time slot and earlier ones
  (`VisitorSubscription::coversTimeSlot`).

## Conventions
- Admin controllers: `app/Http/Controllers/Admin`, API: `app/Http/Controllers/Api/V1`.
- Log important actions with `ActivityLog::record('thing.action', $model, [...])`.
- Vue components in `resources/js/components/*.vue` are mounted from Blade with
  `<div data-vue="Name" data-props='@json($props)'></div>`.
- Tests use a MySQL database `climbing_gym_test` (see `phpunit.xml`).
