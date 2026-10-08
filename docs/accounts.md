# Accounts and access

All inventory pages and actions require an active account. There is no public registration route.

The inventory list is at `/storage`. Use **إضافة صنف جديد** to open the separate entry form at `/storage/create`. Both operators and admins can create items. Saving returns to the inventory list; validation errors return to the form with the entered values preserved. Adding stock to an existing item and withdrawing stock remain available directly from the inventory list.

| Permission | Operator | Admin |
| --- | --- | --- |
| View inventory, reports, and CSV exports | Yes | Yes |
| Create items, add stock, and withdraw stock | Yes | Yes |
| Correct records, cancel movements, archive/restore items, view change history | No | Yes |
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

The PHPUnit configuration forces MySQL and the separate `mod_storage_testing` database. Create that empty database before the first test run. Tests rebuild its tables; never put application data there. Test bootstrap rejects the application database and DATABASE_URL overrides before any test migrations run. Connection credentials come from the local environment. The access-control tests cover authentication, account management, both roles, protected inventory operations, account disabling, and password changes/session invalidation.

## Scope

Login, roles, operation attribution, and permanent inventory change history are implemented. Admins correct records with a reason, cancel movements instead of deleting them, and archive items only at zero stock. New accounts default to the operator role unless an admin role is explicitly assigned. The full admin audit screen with filters/export is a subsequent step.

The application uses MySQL with one creation migration per table. See [database setup](database.md) for the consolidated migration layout and local database setup.

## Operation attribution (step 2)

New items, restocks, and withdrawals store `created_by` (the authenticated user's ID) and `created_by_name` (their name at recording time). The controller calls `createRecorded()` with validated operation fields and the authenticated user. The same insert writes the operation and attribution, and the client cannot supply the actor or recording time.

`created_at` is the server recording time, separate from the user-entered `added_at` or `date`. Item details, restock/withdrawal history, and movement reports show both dates and the recorded name. Recording timestamps display in the configured application timezone, currently Asia/Damascus. Movement reports distinguish initial stock, restocks, and withdrawals.

Existing records retain their original timestamps and receive no guessed user attribution; they display **سجل سابق / المستخدم غير معروف** (Legacy / user unknown). Records created outside the authenticated workflow, such as seed data, also have unknown attribution. The inventory CSV remains a stock summary; it is not an operation-history export.

Account renames or deactivation do not change the stored name. Foreign keys prevent deletion of referenced accounts. Corrections preserve the original creator and recording time, and separately record the admin and before/after values. See [inventory change history](inventory-history.md) for step 3.
