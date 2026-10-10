<?php

namespace App\Services;

use App\Models\Addition;
use App\Models\InventoryAudit;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function createItem(array $data, User $actor): ProductIn
    {
        return DB::transaction(function () use ($data, $actor) {
            $item = $this->insertItem($data, $actor);
            $this->record($item, $item, 'created', $actor, null, null, 0);

            return $item;
        }, 3);
    }

    public function createMovement(string $class, array $data, User $actor): Model
    {
        return DB::transaction(function () use ($class, $data, $actor) {
            $item = ProductIn::lockForUpdate()->findOrFail($data['product_in_id']);
            $this->assertActive($item);
            $stock = $item->current_stock;
            if ($class === Out::class && $data['quantity'] > $stock) {
                $this->reject('الكمية المطلوبة غير متوفرة في المخزون.');
            }
            $movement = $class::createRecorded($data, $actor);
            $this->record($item, $movement, 'created', $actor, null, null, $stock);

            return $movement;
        }, 3);
    }

    public function updateItem(int $id, array $data, string $reason, User $actor): ProductIn
    {
        return DB::transaction(function () use ($id, $data, $reason, $actor) {
            $item = ProductIn::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('correct-inventory', $item);
            $this->assertActive($item);
            if (isset($data['quantity']) && (int) $data['quantity'] !== (int) $item->quantity) {
                $fields = ['name', 'category', 'quantity', 'manufacturer', 'model_type', 'serial_number', 'reciever', 'added_at', 'description'];
                $replacementData = array_intersect_key([...$item->getAttributes(), ...$data], array_flip($fields));

                return $this->cancelItem($id, $reason, $actor, $replacementData);
            }
            $this->edit(ProductIn::class, $id, $data, $reason, $actor);

            return $item->fresh();
        }, 3);
    }

    public function updateMovement(string $class, int $id, array $data, string $reason, User $actor): Model
    {
        $productId = $this->productId($class, $id);

        return DB::transaction(function () use ($class, $id, $productId, $data, $reason, $actor) {
            // Decide between an edit and a replacement only after locking both parents.
            ProductIn::whereIn('id', [$productId, $data['product_in_id'] ?? $productId])->orderBy('id')->lockForUpdate()->get();
            $original = $class::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('correct-inventory', $original);
            $this->assertActive(ProductIn::findOrFail($productId), $original);
            $quantity = (int) ($data['quantity'] ?? $original->quantity);
            $targetId = (int) ($data['product_in_id'] ?? $productId);
            if ($quantity !== (int) $original->quantity) {
                throw ValidationException::withMessages(['quantity' => 'لتغيير الكمية، ألغِ العملية ثم سجّل عملية جديدة بالكمية الصحيحة.']);
            }
            if ($targetId !== $productId) {
                $fields = ['product_in_id', 'quantity', 'date', $class === Out::class ? 'destination' : 'source', 'note'];
                $replacementData = array_intersect_key([...$original->getAttributes(), ...$data], array_flip($fields));

                return $this->replaceMovement($class, $id, $replacementData, $reason, $actor);
            }
            $this->edit($class, $id, $data, $reason, $actor);

            return $original->fresh();
        }, 3);
    }

    public function edit(string $class, int $id, array $data, string $reason, User $actor): void
    {
        $productId = $this->productId($class, $id);
        DB::transaction(function () use ($class, $id, $productId, $data, $reason, $actor) {
            [$item, $record] = $this->locked($class, $id, $productId);
            Gate::forUser($actor)->authorize('correct-inventory', $record);
            $this->assertActive($item, $record);
            $before = $record->getAttributes();
            $stock = $item->current_stock;
            $fields = $class === ProductIn::class
                ? ['name', 'category', 'manufacturer', 'model_type', 'serial_number', 'reciever', 'added_at', 'description']
                : ['date', $class === Out::class ? 'destination' : 'source', 'note'];
            $record->fill(array_intersect_key($data, array_flip($fields)));
            if (! $record->isDirty()) {
                return;
            }
            try {
                $record->save();
            } catch (QueryException $exception) {
                $this->rejectDuplicateSerial($exception);
            }
            $this->record($item, $record, 'edited', $actor, $reason, $before, $stock);
        }, 3);
    }

    public function cancel(string $class, int $id, string $reason, User $actor): void
    {
        $productId = $this->productId($class, $id);
        DB::transaction(function () use ($class, $id, $productId, $reason, $actor) {
            [$item, $record] = $this->locked($class, $id, $productId);
            Gate::forUser($actor)->authorize('correct-inventory', $record);
            $this->assertActive($item, $record);
            $stock = $item->current_stock;
            if ($class === Addition::class && $stock < $record->quantity) {
                $this->reject('لا يمكن إلغاء الإضافة لأن جزءاً من كميتها سُحب من المخزون.');
            }
            $before = $record->getAttributes();
            $record->forceFill([
                'cancelled_at' => now(), 'cancelled_by' => $actor->id,
                'cancelled_by_name' => $actor->name, 'cancellation_reason' => $reason,
            ])->save();
            $this->record($item, $record, 'cancelled', $actor, $reason, $before, $stock);
        }, 3);
    }

    public function archive(int $id, string $reason, User $actor, bool $restore = false): void
    {
        Gate::forUser($actor)->authorize('admin');
        DB::transaction(function () use ($id, $reason, $actor, $restore) {
            $item = ProductIn::lockForUpdate()->findOrFail($id);
            if ($item->cancelled_at) {
                $this->reject('الصنف ملغى ولا يمكن إعادة تفعيله.');
            }
            if ($restore) {
                if (! $item->archived_at) {
                    $this->reject('العنصر غير مؤرشف.');
                }
            } else {
                $this->assertActive($item);
                if ($item->current_stock !== 0) {
                    $this->reject('يمكن أرشفة العنصر فقط عندما يكون رصيده صفراً.');
                }
            }
            $before = $item->getAttributes();
            $stock = $item->current_stock;
            $item->forceFill(['archived_at' => $restore ? null : now()])->save();
            $this->record($item, $item, $restore ? 'restored' : 'archived', $actor, $reason, $before, $stock);
        }, 3);
    }

    public function cancelItem(int $id, string $reason, User $actor, ?array $replacementData = null): ?ProductIn
    {
        return DB::transaction(function () use ($id, $reason, $actor, $replacementData) {
            $item = ProductIn::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('correct-inventory', $item);
            $this->assertUnused($item);
            $before = $item->getAttributes();
            $stock = $item->current_stock;
            $item->forceFill([
                'cancelled_at' => now(), 'cancelled_by' => $actor->id,
                'cancelled_by_name' => $actor->name, 'cancellation_reason' => $reason,
                'archived_at' => now(),
            ])->save();
            $replacement = $replacementData === null ? null : $this->insertItem($replacementData, $actor);
            $this->record($item, $item, 'cancelled', $actor, $reason, $before, $stock,
                $replacement ? ['replacement_id' => $replacement->id, 'replacement_product_id' => $replacement->id, 'replacement_product_name' => $replacement->name] : []);
            if ($replacement) {
                $this->record($replacement, $replacement, 'created', $actor, $reason, null, 0,
                    ['replaces_id' => $item->id, 'replaces_product_id' => $item->id, 'replaces_product_name' => $item->name]);
            }

            return $replacement;
        }, 3);
    }

    public function replaceMovement(string $class, int $id, array $data, string $reason, User $actor): Model
    {
        $productId = $this->productId($class, $id);

        return DB::transaction(function () use ($class, $id, $productId, $data, $reason, $actor) {
            // Always lock both items in ID order when a correction changes the item.
            $items = ProductIn::whereIn('id', [$productId, $data['product_in_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $items->get($productId) ?? ProductIn::findOrFail($productId);
            $target = $items->get($data['product_in_id']) ?? ProductIn::findOrFail($data['product_in_id']);
            $original = $class::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('correct-inventory', $original);
            $this->assertActive($source, $original);
            $this->assertActive($target);
            if ((int) $data['quantity'] !== (int) $original->quantity) {
                throw ValidationException::withMessages(['quantity' => 'لتغيير الكمية، ألغِ العملية ثم سجّل عملية جديدة بالكمية الصحيحة.']);
            }
            $sourceStock = $source->current_stock;
            $targetStock = $target->current_stock;
            $sameItem = $source->id === $target->id;
            $isOut = $class === Out::class;
            $sourceDelta = $isOut ? $original->quantity : -$original->quantity;
            $targetDelta = $isOut ? -$data['quantity'] : $data['quantity'];
            if ($sourceStock + $sourceDelta + ($sameItem ? $targetDelta : 0) < 0 || $targetStock + $targetDelta + ($sameItem ? $sourceDelta : 0) < 0) {
                $this->reject('لا يمكن تصحيح العملية لأن الرصيد الناتج لا يكفي.');
            }
            $before = $original->getAttributes();
            $replacement = $class::createRecorded($data, $actor);
            $original->forceFill([
                'cancelled_at' => now(), 'cancelled_by' => $actor->id,
                'cancelled_by_name' => $actor->name, 'cancellation_reason' => $reason,
            ])->save();
            $oldLink = ['replacement_id' => $replacement->id, 'replacement_product_id' => $target->id, 'replacement_product_name' => $target->name];
            $newLink = ['replaces_id' => $original->id, 'replaces_product_id' => $source->id, 'replaces_product_name' => $source->name];
            if ($isOut) {
                $this->record($source, $original, 'cancelled', $actor, $reason, $before, $sourceStock, $oldLink, $sourceStock + $original->quantity);
                $this->record($target, $replacement, 'created', $actor, $reason, null, $targetStock + ($sameItem ? $original->quantity : 0), $newLink);
            } else {
                $this->record($target, $replacement, 'created', $actor, $reason, null, $targetStock, $newLink, $targetStock + $data['quantity']);
                $this->record($source, $original, 'cancelled', $actor, $reason, $before, $sourceStock + ($sameItem ? $data['quantity'] : 0), $oldLink);
            }

            return $replacement;
        }, 3);
    }

    public function assertUnused(ProductIn $item): void
    {
        $this->assertActive($item);
        if ($item->outs()->exists() || $item->additions()->exists()) {
            $this->reject('لا يمكن إلغاء الصنف أو إعادة إدخاله بعد تسجيل أي إضافة أو سحب، حتى لو ألغيت الحركة.');
        }
    }

    private function insertItem(array $data, User $actor): ProductIn
    {
        try {
            return ProductIn::createRecorded($data, $actor);
        } catch (QueryException $exception) {
            $this->rejectDuplicateSerial($exception);
        }
    }

    private function rejectDuplicateSerial(QueryException $exception): never
    {
        if (($exception->errorInfo[1] ?? null) === 1062 && str_contains($exception->getMessage(), 'active_serial_number')) {
            throw ValidationException::withMessages(['serial_number' => 'الرقم التسلسلي مستخدم لصنف آخر.']);
        }
        throw $exception;
    }

    private function productId(string $class, int $id): int
    {
        // Resolve this before starting the transaction so MySQL's repeatable-read
        // snapshot is not established before a competing stock update completes.
        return $class === ProductIn::class ? $id : $class::findOrFail($id)->product_in_id;
    }

    private function locked(string $class, int $id, int $productId): array
    {
        // Every mutation of an item's stock takes the parent lock first.
        $item = ProductIn::lockForUpdate()->findOrFail($productId);

        return [$item, $class === ProductIn::class ? $item : $class::lockForUpdate()->findOrFail($id)];
    }

    private function assertActive(ProductIn $item, ?Model $record = null): void
    {
        if ($item->archived_at) {
            $this->reject($item->cancelled_at ? 'الصنف ملغى ولا يمكن إجراء عمليات عليه.' : 'العنصر مؤرشف. أعد تفعيله قبل إجراء أي تعديل أو حركة.');
        }
        if ($record && $record->getAttribute('cancelled_at')) {
            $this->reject('هذه العملية ملغاة بالفعل ولا يمكن تعديلها أو إلغاؤها مجدداً.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['inventory' => $message]);
    }

    private function record(ProductIn $item, Model $record, string $action, User $actor, ?string $reason, ?array $before, int $stock, array $links = [], ?int $stockAfter = null): void
    {
        InventoryAudit::create([
            'product_in_id' => $item->id,
            'record_type' => match ($record::class) {
                ProductIn::class => 'item', Addition::class => 'addition', Out::class => 'out'
            },
            'record_id' => $record->id, 'action' => $action,
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => $reason,
            'before_values' => $before, 'after_values' => [...$record->fresh()->getAttributes(), ...$links],
            'stock_before' => $stock, 'stock_after' => $stockAfter ?? $item->current_stock, 'created_at' => now(),
        ]);
    }
}
