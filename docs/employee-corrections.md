# Employee corrections

Employees perform daily inventory work and correct their own records. Admins review all actions in **سجل العمليات** and can correct any record, including legacy records without a known creator. There is no time limit or per-action approval requirement. Ownership is checked by creator ID, not by a saved or current display name, in both HTTP authorization and the locked service transaction.

| Action | Employee | Admin |
| --- | --- | --- |
| View inventory; create items; add or withdraw stock | Any active item | Any active item |
| Edit item name, category, manufacturer, model, serial number, recipient, date, description | Own active items | Any active item |
| Edit movement date, source/destination, notes | Own active movements | Any active movement |
| Change selected movement item | Replace own active movement with same quantity | Replace any active movement with same quantity |
| Correct movement quantity | Cancel own movement and enter a new action | Cancel movement and enter a new action |
| Cancel a movement | Own active movements | Any active movement |
| Cancel or replace an entire item | Own unused items | Any unused item |
| Archive a used item at zero stock; restore an archived item | No | Yes |
| Audit all users, export, manage accounts | No | Yes |
| Correct initial quantity through item edit | Own unused items | Any unused item |
| Delete historical records or change initial quantity after a movement | No | No |

Every correction/cancellation requires a reason of 3–1000 characters. Existing creator information remains unchanged. Audit snapshots save actor, time, reason, original values, resulting values, stock effect, and replacement links.

## Initial entry mistakes

**تعديل بيانات الصنف** opens one prefilled dialog inside the shared item card. The initial quantity field is enabled only before any movement. Saving descriptive changes alone updates the same record; changing quantity automatically cancels the original and creates a corrected item with a new ID in one transaction. There is no separate replacement button or page. The audit page displays the replacement as an ordinary item creation, without superseded creation rows, an extra paired cancellation row, or a corrected-version block. Repeated quantity corrections show only the latest creation. The earlier creations, cancellation snapshots, replacement links, and full CSV history remain saved. The original quantity and serial number remain on the cancelled record; its current stock becomes zero. The replacement may reuse the original serial number, but a generated unique `active_serial_number` column prevents duplicate serials among non-cancelled items. Archived items still reserve their serials because they can be restored.

**إلغاء الصنف** cancels an unused incorrect item without creating a replacement. It disappears from the normal inventory list and remains under **عرض المؤرشف والملغى** and in the audit log. Cancelled items cannot be restored or receive movements.

Item cancellation and initial quantity corrections require no addition or withdrawal ever recorded for the item, including cancelled movements. After the first movement, initial quantity stays locked for employees and admins. Descriptive item corrections remain available. An item lock protects this rule against a simultaneous first movement.

## Movement mistakes

**تعديل العملية** opens one prefilled dialog with item, source/destination, operation date, notes, and reason. Saving details only keeps the same movement ID and records one edit audit. Changing the selected item automatically retains the original as cancelled and creates a linked replacement atomically. Quantity has no editable field in movement dialogs and is immutable in both HTTP and service updates. To change it, cancel the action and enter a new one using the normal addition/withdrawal form. There is no separate correction button or page; cancellation remains a separate action. A stock shortage, validation error, or failed audit save leaves the original and balances intact.

The save handler decides between a detail edit and an item replacement only after acquiring the relevant item/movement locks. Both source and target items are locked in ID order. Final balances must be non-negative. Moving a withdrawal restores the source quantity and withdraws the same quantity from the new item. Moving an addition requires enough stock to reverse it on the source item. Cancelling an addition also requires enough stock to reverse its entire quantity; partial consumption cannot be bypassed through quantity editing.


## Verification and local schema

`EmployeeCorrectionsTest` covers ownership, direct-request restrictions, immutable initial quantities, active serial uniqueness, cancellation history, movement replacements on the same/different item, required reasons, UI capabilities, removed correction URLs, unchanged quantity/item edits, and rollback when the second audit save fails. `InventoryConcurrencyTest` uses separate PHP/MySQL workers to test simultaneous withdrawals, item replacement versus the first movement in either order, and movement cancellation versus a competing withdrawal. All tests require the disposable `mod_storage_testing` database.

The product table's existing creation migration includes cancellation fields and the active-serial constraint. `scripts/upgrade-item-cancellation.php` upgrades the local `mod_storage` database in place after taking a SQL backup and verifies that every original field and record remains unchanged; it does not reset the database or add another migration for the table. See [database setup](database.md).
