<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryAudit extends Model
{
    public const ACTIONS = ['created' => 'تسجيل', 'edited' => 'تعديل', 'cancelled' => 'إلغاء', 'archived' => 'أرشفة', 'restored' => 'إعادة تفعيل'];

    public const TYPES = ['item' => 'الصنف', 'addition' => 'إضافة كمية', 'out' => 'سحب'];

    public const FIELD_LABELS = ['id' => 'رقم السجل', 'product_in_id' => 'رقم الصنف', 'name' => 'الاسم', 'category' => 'التصنيف', 'manufacturer' => 'المصنع', 'model_type' => 'الموديل', 'quantity' => 'الكمية', 'serial_number' => 'الرقم التسلسلي', 'reciever' => 'المستلم', 'description' => 'الوصف', 'added_at' => 'تاريخ العملية', 'date' => 'تاريخ العملية', 'source' => 'المصدر', 'destination' => 'الوجهة', 'note' => 'ملاحظات', 'created_by' => 'رقم المستخدم الأصلي', 'created_by_name' => 'المستخدم الأصلي', 'created_at' => 'وقت التسجيل الأصلي', 'updated_at' => 'وقت التحديث', 'cancelled_at' => 'وقت الإلغاء', 'cancelled_by' => 'رقم مستخدم الإلغاء', 'cancelled_by_name' => 'ألغيت بواسطة', 'cancellation_reason' => 'سبب الإلغاء', 'archived_at' => 'وقت الأرشفة'];

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['before_values' => 'array', 'after_values' => 'array', 'created_at' => 'datetime', 'is_legacy' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductIn::class, 'product_in_id');
    }

    public function tone(): string
    {
        if ($this->is_legacy && ($this->after_values['cancelled_at'] ?? null)) {
            return 'slate';
        }

        if (! $this->is_legacy && $this->stock_after !== $this->stock_before) {
            return $this->stock_after > $this->stock_before ? 'green' : 'red';
        }

        return match ($this->action) {
            'created' => $this->record_type === 'out' ? 'red' : 'green',
            'edited' => 'amber', 'cancelled' => 'slate', 'restored' => 'slate',
            default => 'slate',
        };
    }

    public function operationTitle(): string
    {
        return match ($this->action) {
            'created' => match ($this->record_type) {
                'item' => 'إنشاء صنف جديد',
                'addition' => 'إضافة كمية',
                default => 'سحب كمية',
            },
            'cancelled' => $this->record_type === 'out' ? 'إلغاء سحب · إعادة للمخزون' : 'إلغاء إضافة · خصم من المخزون',
            'edited' => match (count($this->changedFields())) {
                1 => 'تعديل '.self::FIELD_LABELS[array_key_first($this->changedFields())],
                default => 'تعديل بيانات '.(self::TYPES[$this->record_type] ?? 'العملية'),
            },
            'archived' => 'أرشفة الصنف',
            'restored' => 'إعادة تفعيل الصنف',
            default => self::ACTIONS[$this->action] ?? $this->action,
        };
    }

    public function changedFields(): array
    {
        if ($this->action !== 'edited') {
            return [];
        }

        $changes = [];
        foreach (['name', 'category', 'manufacturer', 'model_type', 'serial_number', 'reciever', 'description', 'quantity', 'source', 'destination', 'date', 'added_at', 'note'] as $field) {
            $before = $this->before_values[$field] ?? null;
            $after = $this->after_values[$field] ?? null;
            if ($before !== $after) {
                $changes[$field] = ['before' => $before, 'after' => $after];
            }
        }

        return $changes;
    }

    public function operationFields(): array
    {
        return match ($this->record_type) {
            'item' => ['name', 'category', 'quantity', 'manufacturer', 'model_type', 'serial_number', 'reciever', 'added_at', 'description'],
            'addition' => ['quantity', 'source', 'date', 'note'],
            'out' => ['quantity', 'destination', 'date', 'note'],
            default => [],
        };
    }

    public function detailChanges(): array
    {
        // Dates are shown once in the operation data, rather than compared as timestamps.
        $changes = array_diff_key($this->changedFields(), array_flip(['date', 'added_at']));
        $changes = array_intersect_key($changes, array_flip($this->operationFields()));

        if (in_array($this->action, ['cancelled', 'archived', 'restored'])) {
            $field = $this->record_type === 'item' ? 'archived_at' : 'cancelled_at';
            $before = ! empty($this->before_values[$field]);
            $after = ! empty($this->after_values[$field]);
            if ($before !== $after) {
                $inactive = $this->record_type === 'item' ? 'مؤرشف' : 'ملغاة';
                $active = $this->record_type === 'item' ? 'نشط' : 'سارية';
                $changes['status'] = ['before' => $before ? $inactive : $active, 'after' => $after ? $inactive : $active];
            }
        }

        return $changes;
    }

    public function recordLabel(): string
    {
        return $this->record_type === 'item' && $this->action === 'created'
            ? 'إنشاء صنف جديد' : (self::TYPES[$this->record_type] ?? $this->record_type);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Inventory audit entries cannot be deleted.'));
    }
}
