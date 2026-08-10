# Laravel Inertia JS Kit

One-command Laravel auth boilerplate for a **pure Inertia + React + Tailwind monolith** — no API, no tokens. Installs a session-based `AuthController` (register, login, logout, change password, forgot/reset password, email verification), wires up the routes, publishes matching React/TypeScript pages, and wires React + Tailwind into your build — all from a single artisan command.

This is the monolith counterpart to [sanctum-auth-kit](https://github.com/innonazarene/sanctum-auth-kit) (which does the same thing for a token-based API).

## Requirements

- PHP ^8.1
- Laravel ^10.0 | ^11.0 | ^12.0 | ^13.0
- A Laravel app with (or willing to add) Inertia.js — `inertiajs/inertia-laravel` is installed automatically as a dependency of this package

## Installation

```bash
composer require innonazarene/laravel-inertia-js-kit
php artisan inertia-js-kit:install
npm install
```

That single install command will:

1. Publish `App\Http\Middleware\HandleInertiaRequests` (or patch your existing one) so the authenticated user is shared with every page as `auth.user` — this replaces the API's `/me` endpoint.
2. Register that middleware in `bootstrap/app.php` (Laravel 11+) or `app/Http/Kernel.php` (Laravel 10).
3. Publish `App\Http\Controllers\AuthController`.
4. Publish the auth form requests to `App\Http\Requests\Auth`:
   - `LoginRequest`
   - `RegisterRequest`
   - `ChangePasswordRequest`
   - `ForgotPasswordRequest`
   - `ResetPasswordRequest`
5. Publish `routes/auth.php` and require it from `routes/web.php`.
6. Publish React/TypeScript pages to `resources/js/Pages/Auth`: `Register`, `Login`, `ForgotPassword`, `ResetPassword`, `VerifyEmail`, `ChangePassword`, plus shared `Layouts` (`GuestLayout`, `AuthenticatedLayout`) and `Components` (`TextInput`, `InputLabel`, `InputError`, `PrimaryButton`, `TextLink`), styled with Tailwind.
7. Publish `resources/js/app.tsx`, `resources/css/app.css`, and `resources/views/app.blade.php` if your app doesn't already have them.
8. Add `react`, `react-dom`, `@inertiajs/react`, `tailwindcss`, and the Vite plugins for both to `package.json`.
9. Wire the React and Tailwind Vite plugins into `vite.config.js`/`.ts`.

Run `php artisan migrate` and `npm run dev` (or `npm run build`) afterwards.

Pass `--force` to overwrite files that already exist:

```bash
php artisan inertia-js-kit:install --force
```

## Generated routes

Routes are published to `routes/auth.php` and required from `routes/web.php` — plain session-based web routes, no `/api` prefix.

| Method | URI                            | Auth required | Description                    |
|--------|---------------------------------|:--------------:|----------------------------------|
| GET    | `/register`                    |               | Registration page                |
| POST   | `/register`                    |               | Register + log in                |
| GET    | `/login`                       |               | Login page                       |
| POST   | `/login`                       |               | Log in                           |
| GET    | `/forgot-password`             |               | Forgot-password page              |
| POST   | `/forgot-password`             |               | Send a password reset link       |
| GET    | `/reset-password/{token}`      |               | Reset-password page               |
| POST   | `/reset-password`               |               | Reset password via token         |
| GET    | `/verify-email/{id}/{hash}`    |    signed ✅   | Verify email address             |
| GET    | `/verify-email`                |       ✅       | Verify-email notice page         |
| POST   | `/email/verification-notification` | ✅        | Resend verification email        |
| GET    | `/password/change`             |       ✅       | Change-password page              |
| PUT    | `/password`                    |       ✅       | Change password                  |
| POST   | `/logout`                      |       ✅       | Log out                          |

The `AuthController` redirects to a named `dashboard` route after login/register/email verification — make sure one exists in your app.

If `routes/web.php` doesn't exist yet, the installer creates it. Existing published files are left alone on re-run unless `--force` is passed.

All published files are plain Laravel and React code — edit them freely after installation, this package does not hook into them afterwards.

## License

MIT
