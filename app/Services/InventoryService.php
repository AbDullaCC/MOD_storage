<?php

namespace App\Services;

use App\Models\Addition;
use App\Models\InventoryAudit;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function createItem(array $data, User $actor): ProductIn
    {
        return DB::transaction(function () use ($data, $actor) {
            $item = ProductIn::createRecorded($data, $actor);
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

    public function edit(string $class, int $id, array $data, string $reason, User $actor): void
    {
        $productId = $this->productId($class, $id);
        DB::transaction(function () use ($class, $id, $productId, $data, $reason, $actor) {
            [$item, $record] = $this->locked($class, $id, $productId);
            $this->assertActive($item, $record);
            $before = $record->getAttributes();
            $stock = $item->current_stock;
            $record->fill($data);
            if (! $record->isDirty()) {
                return;
            }
            $record->save();
            $this->record($item, $record, 'edited', $actor, $reason, $before, $stock);
        }, 3);
    }

    public function cancel(string $class, int $id, string $reason, User $actor): void
    {
        $productId = $this->productId($class, $id);
        DB::transaction(function () use ($class, $id, $productId, $reason, $actor) {
            [$item, $record] = $this->locked($class, $id, $productId);
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
        DB::transaction(function () use ($id, $reason, $actor, $restore) {
            $item = ProductIn::lockForUpdate()->findOrFail($id);
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
            $this->reject('العنصر مؤرشف. أعد تفعيله قبل إجراء أي تعديل أو حركة.');
        }
        if ($record && $record->getAttribute('cancelled_at')) {
            $this->reject('هذه العملية ملغاة بالفعل ولا يمكن تعديلها أو إلغاؤها مجدداً.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['inventory' => $message]);
    }

    private function record(ProductIn $item, Model $record, string $action, User $actor, ?string $reason, ?array $before, int $stock): void
    {
        InventoryAudit::create([
            'product_in_id' => $item->id,
            'record_type' => match ($record::class) {
                ProductIn::class => 'item', Addition::class => 'addition', Out::class => 'out'
            },
            'record_id' => $record->id, 'action' => $action,
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => $reason,
            'before_values' => $before, 'after_values' => $record->fresh()->getAttributes(),
            'stock_before' => $stock, 'stock_after' => $item->current_stock, 'created_at' => now(),
        ]);
    }
}
