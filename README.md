# نظام إدارة المخزون

Arabic inventory application with username login, employee/admin permissions, inventory corrections, and audit history. Requires PHP 8.3+, MySQL, Composer, and Node/npm for local asset builds.

Install dependencies with `composer install` and `npm ci`, then run `npm run build` before opening the app or running tests. Styling uses compiled assets rather than the Tailwind development CDN.

`php artisan test` only targets the separate disposable `mod_storage_testing` MySQL database. Never point tests at application data.

Project documentation:

- [Database setup](docs/database.md)
- [Accounts and permissions](docs/accounts.md)
- [Employee corrections](docs/employee-corrections.md)
- [Audit page](docs/audit-screen.md)
- [Demo scenarios](docs/demo-data.md)
- [Production preparation and InfinityFree staging](docs/deployment.md)

Laravel is licensed under the [MIT license](https://opensource.org/licenses/MIT).
