# Local demo inventory

The demo keeps all existing login accounts and replaces only items, additions, withdrawals, and inventory audits. New actions use `InventoryService`, including real stock calculations, ownership checks, correction links, and before/after snapshots. Recording times progress across the last twelve days. One older item and withdrawal deliberately lack saved audits or a known creator.

To repeat the reset locally:

```powershell
php scripts/reset-demo-inventory.php C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe
```

This is destructive to existing inventory. The script requires the local `mod_storage` MySQL database, creates a full SQL backup under `storage/app/backups` before changing anything, and deletes/recreates the inventory in one transaction with foreign keys enabled. It checks that account data is unchanged before committing. It does not reset migrations or login credentials. The seeder is not attached to `DatabaseSeeder`, so ordinary seeding does not wipe data.

| Item | Demo case | Current stock |
| --- | --- | ---: |
| حاسوب محمول Dell Latitude | Addition, withdrawal, destination and recipient-note correction | 19 |
| حبر طابعة HP 59A | Addition source corrected, followed by withdrawal | 14 |
| كابل شبكة بطول 3 أمتار | Wrong withdrawal cancelled and reentered with correct quantity | 30 |
| فأرة لاسلكية Logitech | Wrong addition cancelled and reentered with correct quantity | 26 |
| لوحة مفاتيح عربية | Initial quantity corrected twice before movements; only final creation shown | 14 |
| شاشة Samsung مقاس 24 بوصة | Name and description edited without changing stock | 8 |
| طابعة أُدخلت مرتين | Unused duplicate item cancelled entirely | 0 |
| راوتر فرع قديم | Fully withdrawn and archived by admin | 0 |
| مزود طاقة احتياطي UPS | Fully withdrawn, archived, restored, then restocked | 4 |
| سويتش شبكة 8 منافذ | Empty but active, ready for restocking | 0 |
| قرص تخزين SSD سعة 500 GB | Low remaining stock | 1 |
| جهاز عرض Epson | Unused admin-owned item | 3 |
| خادم ملفات صغير | Employee withdrawal cancelled by admin | 1 |
| كابل HDMI بطول مترين | Wrong withdrawal moved to USB-C; addition moved here from USB-C | 27 |
| كابل USB-C | Corrected movement item selection, retaining quantity | 20 |
| كرسي مكتب من السجلات السابقة | Legacy item and withdrawal without known creator | 4 |

Use the existing admin and entry employee logins to compare permissions. The audit has enough records for pagination and filters, and expanded details include realistic suppliers, destinations, notes, and correction reasons. Archived/cancelled items appear under the inventory's archived/cancelled view.

For blocked cases, try withdrawing more than the remaining SSD stock, editing the initial quantity of a laptop with movements, or editing the admin-owned projector as the employee. These requests should fail without saving an operation. Rejected attempts do not become fake audit rows.

`DemoInventoryTest` runs on the separate disposable MySQL database and verifies expected balances, action types, actor attribution, legacy rows, compact/full audit totals, page rendering, preserved accounts, and absence of negative stock or future audit dates.
