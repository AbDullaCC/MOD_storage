# Local database and migrations

The application now uses Laragon MySQL 8.4 at `127.0.0.1:3306`.

| Database | Purpose |
| --- | --- |
| `mod_storage` | Local application inventory and accounts |
| `mod_storage_testing` | Disposable tables rebuilt by automated tests |

Local connection settings are stored in the untracked `.env` file. `.env.example` contains the MySQL defaults for a fresh checkout. PHPUnit forces the test database; its bootstrap also checks the database name before allowing migrations to run.

## One creation migration per table

Each migration under `database/migrations` defines one complete table and drops that same table in `down()`:

- `create_users_table`: account fields, role, and active status.
- `create_password_reset_tokens_table`: password-reset tokens.
- `create_failed_jobs_table`: failed jobs.
- `create_personal_access_tokens_table`: API tokens.
- `create_product_ins_table`: items, initial stock, creator attribution, and timestamps.
- `create_outs_table`: withdrawals, creator attribution, and timestamps.
- `create_additions_table`: restocks, creator attribution, and timestamps.

The filenames retain their timestamp prefixes so dependent tables are created after `users` and `product_ins`. Laravel maintains its own `migrations` tracking table automatically.

## Fresh environment

Create the two databases using Laragon's database tool or MySQL:

```sql
CREATE DATABASE mod_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE mod_storage_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Configure `.env`, then run:

```sh
php artisan migrate
php artisan app:create-admin admin@example.com --name="Admin"
php artisan test
```

## Consolidation and existing databases

These migrations are the new complete schema baseline. They are intended for empty databases. Changing a creation migration does **not** update a table where that migration has already run.

During early local development, rebuilding a disposable database with `php artisan migrate:fresh` applies edited creation migrations but **deletes all tables and data in the selected database**, including accounts. Recreate an admin afterward. For deployed databases that must retain data, use additional upgrade migrations instead of rewriting applied history.

The local switch from SQLite created a fresh MySQL schema, copied the application tables while preserving IDs/password hashes/timestamps, and verified every copied field. MySQL has its own migration history matching the consolidated files; the old SQLite migration history was not imported. SQLite and environment backups plus a transfer verification report are in the ignored `storage/app/backups` directory. The original SQLite file is retained as an archive and is no longer used by the app. Do not run the consolidated migration history against that old file.
