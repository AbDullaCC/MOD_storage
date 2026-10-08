# Inventory change history (step 3)

Admins open **سجل التغييرات** from an item's details. Each entry shows the operation, actor's name at the time, server recording time, stock before/after, and the saved field values. Corrections require a reason of 3–1000 characters. Changes recorded before this feature was enabled are not reconstructed.

For newly recorded operations, the before/after comparison shows the actual stock balance. Their recorded fields appear separately because the record did not exist beforehand. Edits and cancellations also show the original field values alongside the resulting values; an unset field is labelled explicitly. Green indicates incoming stock, red indicates outgoing stock/cancellation, and amber identifies corrections.

## Corrections and cancellation

- Editing descriptive fields or operation dates retains the original creator and quantity. The audit entry keeps the original and corrected values plus the admin's reason.
- Cancelling a withdrawal restores its quantity to stock. Cancelling a restock removes its quantity only when the remaining stock can cover it. The original movement stays visible with its cancellation details.
- A cancelled movement cannot be edited or cancelled again. Stock totals and the CSV summary exclude cancelled movements; the movement report still shows them.
- Items can be archived only at zero stock. Archiving retains their movements and audit history and hides them from the normal inventory list. Use **عرض المؤرشف أيضاً**, then the item's history page to restore it with a reason. Archived items must be restored before further changes.

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
