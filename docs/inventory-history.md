# Inventory change history (step 3)

Admins open **سجل العمليات** from an item's details. This opens the single operations page with that item already selected. Saved audit entries show the operation, actor's name at the time, server recording time, stock before/after, and saved field values. Corrections require a reason of 3–1000 characters. Earlier operations also appear as clearly labelled legacy rows with their current stored fields; no historical balances or edits are invented. See [operations log](audit-screen.md).

For newly recorded operations, the before/after comparison shows the actual stock balance. Their recorded fields appear separately because the record did not exist beforehand. Edits and cancellations also show the original field values alongside the resulting values; an unset field is labelled explicitly. Green indicates incoming stock, red indicates outgoing stock/cancellation, and amber identifies corrections.

## Corrections and cancellation

- Editing descriptive fields or operation dates retains the original creator and quantity. The audit entry keeps the original and corrected values plus the admin's reason.
- Cancelling a withdrawal restores its quantity to stock. Cancelling a restock removes its quantity only when the remaining stock can cover it. The original movement stays visible with its cancellation details.
- A cancelled movement cannot be edited or cancelled again. Stock totals and the inventory CSV summary exclude cancelled movements; the operations log retains their entries and cancellation details.
- Items can be archived only at zero stock. Archiving retains their movements and audit history and hides them from the normal inventory list. Use **عرض المؤرشف أيضاً**, then the item's **سجل العمليات** link to restore it with a reason. Archived items must be restored before further changes.

## How the code saves history

`StorageController` validates operation fields and correction reasons. `InventoryService` performs the inventory change and inserts its audit entry in the same database transaction:

```php
DB::transaction(function () use ($item, $record, $actor, $reason) {
    $before = $record->getAttributes();
    // Save the validated correction or cancellation here.
    // Insert actor, reason, before/after snapshots, and stock balances.
}, 3);
```

If saving the audit fails, the inventory change rolls back too. Stock changes lock the parent item first so competing withdrawals/cancellations are checked against the current balance. Actor identities, cancellation metadata, and recording times come from the server rather than submitted fields.

There are no application routes to edit or delete audit entries. Model guards reject deleting inventory records and updating/deleting audit entries; foreign keys prevent deleting referenced items/accounts. These controls preserve history through the application's workflows. A database administrator with direct SQL access can still change database contents.

The MySQL feature tests cover snapshots, required reasons, authorization, stock reversals, repeated cancellation, archive/restore, rollback on audit failure, exports, escaped content, and actor-name preservation.

## Inventory correctness verification

`InventoryConcurrencyTest` runs real withdrawals through `InventoryService` in two independent PHP processes with separate MySQL connections. It uses `DatabaseMigrations` so fixtures are committed and visible to both workers; the normal test bootstrap and worker both reject any database other than `mod_storage_testing`. No application data is used or changed.

The first worker pauses inside the service transaction immediately after acquiring the item row lock. The second starts its competing withdrawal and must remain blocked until the first is released. Both processes are stopped before database cleanup, including on test failure.

- With 10 units and competing requests for 7 each, one withdrawal succeeds, the second is rejected for insufficient stock, and 3 units remain. The rejected request creates neither a withdrawal nor an audit entry.
- With 10 units and competing requests for 4 and 6, both succeed and stock ends at zero. Their audit balances must form the sequence 10 → 6 → 0, with the correct user, quantity, and movement ID for each entry.

Run these cases with `php artisan test --filter=InventoryConcurrencyTest`, or run the complete correctness, permission, and audit checks with `php artisan test`. The database-locking protection already existed; this adds direct verification of simultaneous withdrawals without changing application behavior.
