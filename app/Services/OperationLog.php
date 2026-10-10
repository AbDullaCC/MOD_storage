<?php

namespace App\Services;

use App\Models\InventoryAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OperationLog
{
    public function query(): Builder
    {
        $entries = DB::table('inventory_audits')->select('inventory_audits.*')->selectRaw('0 AS is_legacy');
        foreach (['item' => 'product_ins', 'addition' => 'additions', 'out' => 'outs'] as $type => $table) {
            $fields = $type === 'item'
                ? ['id', 'name', 'category', 'manufacturer', 'model_type', 'quantity', 'serial_number', 'reciever', 'description', 'added_at', 'created_by', 'created_by_name', 'archived_at', 'created_at', 'updated_at']
                : ['id', 'product_in_id', 'quantity', 'date', $type === 'out' ? 'destination' : 'source', 'note', 'created_by', 'created_by_name', 'cancelled_at', 'cancelled_by', 'cancelled_by_name', 'cancellation_reason', 'created_at', 'updated_at'];
            $json = implode(', ', array_map(static function ($field) use ($table) {
                $value = $table.'.'.$field;
                if (in_array($field, ['added_at', 'date', 'created_at', 'updated_at', 'cancelled_at', 'archived_at'])) {
                    $value = "DATE_FORMAT($value, '%Y-%m-%d %H:%i:%s')";
                }

                return "'".$field."', ".$value;
            }, $fields));
            $productId = $type === 'item' ? "$table.id" : "$table.product_in_id";
            $date = $type === 'item' ? 'added_at' : 'date';
            $legacy = DB::table($table)->selectRaw("$table.id, $productId AS product_in_id, ? AS record_type, $table.id AS record_id, 'created' AS action, $table.created_by AS actor_id, COALESCE($table.created_by_name, ?) AS actor_name, NULL AS reason, NULL AS before_values, JSON_OBJECT($json) AS after_values, NULL AS stock_before, NULL AS stock_after, COALESCE($table.created_at, $table.$date) AS created_at, 1 AS is_legacy", [$type, 'سجل سابق / المستخدم غير معروف'])
                ->whereNotExists(function ($query) use ($type, $table) {
                    $query->selectRaw('1')->from('inventory_audits')
                        ->where('record_type', $type)->where('action', 'created')
                        ->whereColumn('record_id', "$table.id");
                });
            $entries->unionAll($legacy);
        }

        // Legacy rows are a read-only view of existing operations, never invented audit entries.
        return InventoryAudit::query()->fromSub($entries, 'inventory_audits');
    }
}
