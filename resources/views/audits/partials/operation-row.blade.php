@php
    $tone = $event->tone();
    $delta = $event->is_legacy ? null : $event->stock_after - $event->stock_before;
    $values = $event->after_values ?? [];
    $changes = $event->action === 'edited' ? $event->detailChanges() : [];
    $detailId = 'operation-'.($event->is_legacy ? 'legacy-' : 'audit-').$event->record_type.'-'.$event->id;
@endphp
<tbody class="operation-group audit-tone-{{ $tone }}" @if($event->is_legacy) data-legacy-record="{{ $event->record_type }}-{{ $event->record_id }}" @else data-audit-event="{{ $event->id }}" @endif>
    <tr class="operation-row">
        <td class="operation-action">
            <div class="operation-headline"><span class="operation-marker" aria-hidden="true"></span><strong>{{ $event->operationTitle() }}</strong>
                @if(in_array($event->action, ['created', 'cancelled']) && isset($values['quantity']))<span class="operation-quantity"><bdi>{{ $values['quantity'] }}</bdi> وحدة</span>@endif
            </div>
            @if($changes)
                @foreach(array_slice($changes, 0, 2, true) as $field => $change)
                    <div class="operation-change">@if(count($changes) > 1)<span>{{ \App\Models\InventoryAudit::FIELD_LABELS[$field] }}: </span>@endif<bdi class="change-before">{{ $change['before'] === null || $change['before'] === '' ? 'غير محدد' : $change['before'] }}</bdi><span aria-label="أصبح"> ← </span><bdi class="change-after">{{ $change['after'] === null || $change['after'] === '' ? 'غير محدد' : $change['after'] }}</bdi></div>
                @endforeach
                @if(count($changes) > 2)<span class="operation-meta">و{{ count($changes) - 2 }} حقول أخرى في التفاصيل</span>@endif
            @else
                @if($values['destination'] ?? null)<div class="operation-context">إلى <strong dir="auto">{{ $values['destination'] }}</strong></div>@endif
                @if($values['source'] ?? null)<div class="operation-context">من <strong dir="auto">{{ $values['source'] }}</strong></div>@endif
            @endif
            @if($event->is_legacy)<span class="operation-meta">سجل سابق @if($values['cancelled_at'] ?? null) · <strong>ملغاة</strong>@endif</span>@endif
        </td>
        <td class="operation-item">
            <a href="{{ route('audits.itemCard', $event->product_in_id) }}" data-item-card aria-haspopup="dialog" dir="auto">{{ $event->product?->name ?? 'صنف غير متاح' }}</a>
            @if($event->product?->archived_at)<span class="operation-meta">مؤرشف</span>@endif
        </td>
        <td class="operation-balance" data-label="الرصيد">
            @if($event->is_legacy)<span class="operation-meta">غير متاح تاريخياً</span>
            @elseif($delta === 0)<span class="operation-unchanged">دون تغيير</span>
            @else
                <div class="operation-stock" aria-label="الرصيد قبل {{ $event->stock_before }} وبعد {{ $event->stock_after }}"><span>{{ $event->stock_before }}</span><span class="balance-arrow" aria-hidden="true">←</span><strong>{{ $event->stock_after }}</strong></div>
                <span class="operation-delta">@if($delta > 0)زيادة <bdi>+{{ $delta }}</bdi>@else نقص <bdi>−{{ abs($delta) }}</bdi>@endif</span>
            @endif
        </td>
        <td class="operation-user" data-label="بواسطة"><strong dir="auto">{{ $event->actor_name }}</strong></td>
        <td class="operation-date" data-label="التسجيل"><time datetime="{{ $event->created_at->toIso8601String() }}"><bdi>{{ $event->created_at->format('Y-m-d') }}</bdi><bdi class="operation-meta">{{ $event->created_at->format('H:i:s') }}</bdi></time></td>
        <td class="operation-toggle-cell"><button class="ui-button operation-toggle" type="button" data-operation-toggle aria-controls="{{ $detailId }}" aria-expanded="false" aria-label="تفاصيل {{ $event->operationTitle() }} — {{ $event->product?->name ?? 'صنف غير متاح' }}">تفاصيل <x-icon name="chevron" /></button></td>
    </tr>
    <tr id="{{ $detailId }}" class="operation-detail-row" hidden>
        <td colspan="6"><div class="operation-detail-panel">
            @include('storage.partials.audit-values', ['event' => $event])
        </div></td>
    </tr>
</tbody>
