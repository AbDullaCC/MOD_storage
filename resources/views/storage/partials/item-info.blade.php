@php
    $info = $info ?? [];
    $prefix = $prefix ?? 'modal';
    $fields = [
        'stock' => ['label' => 'المخزون', 'icon' => 'box'],
        'category' => ['label' => 'التصنيف', 'icon' => 'box'],
        'manufacturer' => ['label' => 'المصنع', 'icon' => 'box'],
        'model' => ['label' => 'الموديل', 'icon' => 'box'],
        'sn' => ['label' => 'الرقم التسلسلي', 'icon' => 'key'],
        'receiver' => ['label' => 'تم الاستلام بواسطة', 'icon' => 'users'],
        'date' => ['label' => 'تاريخ إضافة الصنف', 'icon' => 'clock'],
        'recorded-by' => ['label' => 'أنشأ الصنف', 'icon' => 'users'],
        'recorded-at' => ['label' => 'وقت التسجيل', 'icon' => 'clock'],
        'desc' => ['label' => 'الوصف', 'icon' => 'edit'],
    ];
@endphp
<dl class="details-grid info-grid" aria-label="معلومات الصنف">
    @foreach($fields as $field => $config)
        <div class="info-field info-field-{{ $field }} @if($field === 'stock' && isset($info['stock']) && $info['stock'] <= 0) info-stock-empty @endif @if(in_array($field, ['recorded-at', 'desc'])) info-field-wide @endif">
            <dt>{{ $config['label'] }}</dt>
            <dd id="{{ $prefix }}-{{ $field }}" dir="auto">{{ array_key_exists($field, $info) ? ($info[$field] === null || $info[$field] === '' ? 'غير محدد' : $info[$field]) : '…' }}</dd>
        </div>
    @endforeach
</dl>
