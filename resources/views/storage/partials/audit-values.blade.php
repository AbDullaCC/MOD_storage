@php
    $values = $event->after_values ?? [];
    $changes = $event->record_type === 'item' ? $event->detailChanges() : [];
    $labels = \App\Models\InventoryAudit::FIELD_LABELS + ['status' => 'الحالة'];
    $fields = array_diff($event->operationFields(), array_keys($changes));
    $displayValue = static function ($field, $value) {
        if ($value === null || $value === '') {
            return 'غير محدد';
        }
        return in_array($field, ['date', 'added_at']) ? substr($value, 0, 10) : $value;
    };
    $reason = $event->reason ?: ($event->is_legacy ? ($values['cancellation_reason'] ?? null) : null);
@endphp
<div class="audit-values audit-values-embedded">
    <section class="audit-detail-section" aria-label="بيانات العملية">
        <div class="audit-record-heading"><h4>بيانات العملية</h4>@if($event->is_legacy)<p>سجل سابق · هذه البيانات الحالية للعملية، ولا يوجد رصيد تاريخي محفوظ.</p>@endif</div>
        <dl class="audit-record-fields info-grid">
            @foreach($fields as $field)
                <div class="info-field info-field-{{ $field }} @if(in_array($field, ['note', 'description'])) audit-field-wide @endif"><dt>{{ $labels[$field] }}</dt><dd class="whitespace-pre-wrap" dir="auto">{{ $displayValue($field, $values[$field] ?? null) }}</dd></div>
            @endforeach
        </dl>
    </section>
    @if($changes)
        <section class="audit-detail-section" aria-label="التغييرات">
            <div class="audit-record-heading"><h4>ما الذي تغيّر؟</h4></div>
            <div class="audit-values-table audit-field-comparison">
                <table aria-label="مقارنة بيانات العملية">
                    <thead><tr><th scope="col">الحقل</th><th scope="col">قبل</th><th scope="col">بعد</th></tr></thead>
                    <tbody>
                        @foreach($changes as $field => $change)
                            <tr><th scope="row">{{ $labels[$field] }}</th><td class="whitespace-pre-wrap" dir="auto">{{ $displayValue($field, $change['before']) }}</td><td class="whitespace-pre-wrap" dir="auto">{{ $displayValue($field, $change['after']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    @if(! $event->is_legacy && $event->stock_before !== $event->stock_after)
        <section class="audit-detail-section" aria-label="أثر العملية على المخزون">
            <div class="audit-record-heading"><h4>أثر العملية على المخزون</h4></div>
            <div class="audit-values-table audit-balance-table">
                <table aria-label="رصيد المخزون قبل العملية وبعدها">
                    <thead><tr><th scope="col">المخزون</th><th scope="col">قبل العملية</th><th scope="col">بعد العملية</th></tr></thead>
                    <tbody><tr><th scope="row">الرصيد المتاح</th><td>{{ $event->stock_before }}</td><td>{{ $event->stock_after }}</td></tr></tbody>
                </table>
            </div>
        </section>
    @endif
    @if($reason)<p class="audit-reason"><strong>السبب</strong><span class="whitespace-pre-wrap" dir="auto">{{ $reason }}</span></p>@endif
</div>
