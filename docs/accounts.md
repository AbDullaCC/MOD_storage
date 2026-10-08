# Accounts and access

All inventory pages and actions require an active account. There is no public registration route.

| Permission | Operator | Admin |
| --- | --- | --- |
| View inventory, reports, and CSV exports | Yes | Yes |
| Create items, add stock, and withdraw stock | Yes | Yes |
| Edit or delete existing inventory records | No | Yes |
| Create accounts, change roles, disable accounts, reset passwords | No | Yes |
| Change own password and log out | Yes | Yes |

Admins manage accounts from **إدارة المستخدمين**. Disabling an account blocks new logins and ends access on its next protected request. Resetting its password invalidates other sessions on their next protected request. An admin cannot disable or demote their own account. Accounts are disabled rather than deleted.

## Set up another environment

Apply the migrations and create the first admin from a trusted terminal:

```sh
php artisan migrate
php artisan app:create-admin admin@example.com --name="Admin"
```

The command asks for a password and confirmation without displaying them. It refuses to overwrite an existing account. No default accounts or passwords are seeded. Use the admin screen to create operators and additional admins.

Users can change their password from **تغيير كلمة المرور**. Login attempts are rate limited. Passwords are hashed by Laravel; logout invalidates the session and regenerates the CSRF token.

## Verification

```sh
php artisan test
```

The PHPUnit configuration forces an isolated in-memory SQLite database. The access-control tests cover authentication, account management, both roles, protected inventory operations, account disabling, and password changes/session invalidation.

## Scope

This is the first implementation step: login and roles. Operation attribution, permanent audit history, and replacing inventory deletions with reversals are subsequent steps. Existing edit/delete behavior is currently restricted to admins. Existing users receive the operator role when the migration runs.
