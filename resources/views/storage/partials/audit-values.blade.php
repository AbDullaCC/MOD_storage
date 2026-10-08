@php
    $isCreation = $event->action === 'created';
    $beforeValues = $event->before_values ?? [];
    $afterValues = $event->after_values ?? [];
    $keys = array_unique(array_merge(array_keys($beforeValues), array_keys($afterValues)));
    $displayValue = static fn ($value) => $value === null || $value === '' ? 'غير محدد' : $value;
@endphp
<details class="audit-values">
    <summary><span>{{ $isCreation ? 'عرض تفاصيل العملية وأثرها على المخزون' : 'عرض القيم قبل وبعد' }} <span class="audit-id mr-2">سجل #{{ $event->id }}</span></span><x-icon name="chevron" /></summary>
    <div class="audit-values-table audit-balance-table">
        <table aria-label="رصيد المخزون قبل العملية وبعدها">
            <thead><tr><th>المخزون</th><th>قبل العملية</th><th>بعد العملية</th></tr></thead>
            <tbody><tr><td>الرصيد المتاح</td><td>{{ $event->stock_before }}</td><td>{{ $event->stock_after }}</td></tr></tbody>
        </table>
    </div>
    @if($isCreation)
        <div class="audit-record-heading"><h4>بيانات العملية عند التسجيل</h4><p>هذه عملية جديدة؛ المقارنة أعلاه توضح الرصيد قبل تسجيلها وبعده.</p></div>
        <dl class="audit-record-fields">
            @foreach($afterValues as $key => $value)
                @if($value !== null && $value !== '')
                    <div><dt>{{ $labels[$key] ?? $key }}</dt><dd class="whitespace-pre-wrap" dir="auto">{{ $value }}</dd></div>
                @endif
            @endforeach
        </dl>
    @else
        <div class="audit-record-heading"><h4>{{ $event->action === 'edited' ? 'الحقول التي تم تعديلها' : 'بيانات العملية قبل التغيير وبعده' }}</h4></div>
        <div class="audit-values-table audit-field-comparison">
            <table aria-label="مقارنة بيانات العملية">
                <thead><tr><th>الحقل</th><th>قبل</th><th>بعد</th></tr></thead>
                <tbody>
                @foreach($keys as $key)
                    @php
                        $before = $beforeValues[$key] ?? null;
                        $after = $afterValues[$key] ?? null;
                    @endphp
                    @if($event->action !== 'edited' || $before !== $after)
                        <tr><td>{{ $labels[$key] ?? $key }}</td><td class="whitespace-pre-wrap">{{ array_key_exists($key, $beforeValues) ? $displayValue($before) : 'غير متاح' }}</td><td class="whitespace-pre-wrap">{{ array_key_exists($key, $afterValues) ? $displayValue($after) : 'غير متاح' }}</td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</details>
