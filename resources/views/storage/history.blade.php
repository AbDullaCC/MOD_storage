@extends('layouts.account')
@section('title', 'سجل التغييرات')
@section('content')
@php
    $actions = ['created' => 'تسجيل', 'edited' => 'تعديل', 'cancelled' => 'إلغاء', 'archived' => 'أرشفة', 'restored' => 'إعادة تفعيل'];
    $types = ['item' => 'إنشاء صنف جديد', 'addition' => 'إضافة كمية', 'out' => 'سحب'];
    $tones = ['edited' => 'amber', 'cancelled' => 'red', 'archived' => 'slate', 'restored' => 'green'];
    $labels = ['id' => 'رقم السجل', 'product_in_id' => 'رقم الصنف', 'name' => 'الاسم', 'category' => 'التصنيف', 'manufacturer' => 'المصنع', 'model_type' => 'الموديل', 'quantity' => 'الكمية', 'serial_number' => 'الرقم التسلسلي', 'reciever' => 'المستلم', 'description' => 'الوصف', 'added_at' => 'تاريخ العملية', 'date' => 'تاريخ العملية', 'source' => 'المصدر', 'destination' => 'الوجهة', 'note' => 'ملاحظات', 'created_by' => 'رقم المستخدم الأصلي', 'created_by_name' => 'المستخدم الأصلي', 'created_at' => 'وقت التسجيل الأصلي', 'updated_at' => 'وقت التحديث', 'cancelled_at' => 'وقت الإلغاء', 'cancelled_by' => 'رقم مستخدم الإلغاء', 'cancelled_by_name' => 'ألغيت بواسطة', 'cancellation_reason' => 'سبب الإلغاء', 'archived_at' => 'وقت الأرشفة'];
@endphp
<div class="history-page">
    <div class="breadcrumb"><a href="{{ route('storage.index') }}">المخزون</a><x-icon name="chevron" /><span>{{ $product->name }}</span><x-icon name="chevron" /><span>سجل التغييرات</span></div>
    <div class="page-heading">
        <div><div class="eyebrow">المراجعة والمتابعة</div><h1>سجل التغييرات</h1><p>كل عملية وتعديل، بتفاصيل واضحة في مكان واحد.</p></div>
        <a href="{{ route('storage.index', ['include_archived' => 1]) }}" class="ui-button"><x-icon name="arrow" /> العودة للمخزون</a>
    </div>
    <section class="ui-panel item-summary" aria-label="ملخص الصنف">
        <div class="summary-product">
            <span class="section-icon"><x-icon name="box" /></span>
            <div><h2>{{ $product->name }}</h2><p>{{ $product->category }} <span class="mx-2">·</span><span class="status-badge {{ $product->archived_at ? 'status-amber' : 'status-green' }}"><span class="status-dot"></span>{{ $product->archived_at ? 'مؤرشف' : 'نشط' }}</span></p></div>
        </div>
        <div class="summary-stat"><span>الرصيد الحالي</span><strong>{{ $product->current_stock }}</strong><small>وحدة</small></div>
        <div class="summary-stat"><span>العمليات المسجلة</span><strong>{{ $events->total() }}</strong><small>عملية</small></div>
    </section>
    @if($product->archived_at)
    <section class="ui-panel restore-panel">
        <div class="panel-heading"><span class="section-icon status-amber"><x-icon name="archive" /></span><div><h2>هذا الصنف مؤرشف</h2><p>أعد تفعيله لإجراء عمليات جديدة عليه. يبقى سجله السابق محفوظاً.</p></div></div>
        <form method="POST" action="{{ route('storage.restoreItem', $product->id) }}">
            @csrf
            <label for="restore-reason">سبب إعادة التفعيل<input id="restore-reason" name="reason" required minlength="3" maxlength="1000" placeholder="وضّح سبب إعادة الصنف للمخزون" value="{{ old('reason') }}"></label>
            <button class="ui-button ui-button-success"><x-icon name="history" /> إعادة تفعيل الصنف</button>
        </form>
    </section>
    @endif
    <div class="history-section-heading">
        <div><h2>السجل الزمني</h2><p>مرتّب من الأحدث إلى الأقدم</p></div>
        <span class="form-hint"><x-icon name="clock" /> التوقيت: <bdi>{{ config('app.timezone') }}</bdi></span>
    </div>
    @if($events->isNotEmpty())<div class="audit-list">@endif
    @forelse($events as $event)
    @php
        $tone = $event->action === 'created' ? ($event->record_type === 'out' ? 'red' : 'green') : $tones[$event->action];
        $stockDelta = $event->stock_after - $event->stock_before;
        $stockDirection = $stockDelta > 0 ? 'increase' : ($stockDelta < 0 ? 'decrease' : 'unchanged');
    @endphp
    <article class="ui-panel audit-card audit-tone-{{ $tone }}" data-audit-event="{{ $event->id }}">
        <span class="audit-dot" aria-hidden="true"></span>
        <div class="audit-topline">
            <div class="audit-title"><span class="status-badge status-{{ $tone }}">{{ $actions[$event->action] }}</span><h3>{{ $types[$event->record_type] }} <span class="audit-id">#{{ $event->record_id }}</span></h3></div>
            <div class="audit-time"><x-icon name="clock" /><time datetime="{{ $event->created_at->toIso8601String() }}"><bdi>{{ $event->created_at->format('Y-m-d') }}</bdi> <span class="mx-1">·</span> <bdi>{{ $event->created_at->format('H:i:s') }}</bdi></time></div>
        </div>
        <div class="audit-body">
            <div class="audit-actor"><span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($event->actor_name, 0, 1)) }}</span><div><small>بواسطة</small><strong dir="auto">{{ $event->actor_name }}</strong></div></div>
            <div class="stock-change stock-{{ $stockDirection }}" aria-label="تغير الرصيد"><div><small>الرصيد قبل</small><strong>{{ $event->stock_before }}</strong></div><x-icon name="arrow" /><div><small>الرصيد بعد</small><strong>{{ $event->stock_after }}</strong></div><span class="stock-delta">{{ $stockDelta > 0 ? 'زيادة' : ($stockDelta < 0 ? 'نقص' : 'دون تغيير') }} @if($stockDelta !== 0)<bdi>{{ $stockDelta > 0 ? '+' : '−' }}{{ abs($stockDelta) }}</bdi>@endif</span></div>
        </div>
        @if($event->reason)<p class="audit-reason"><strong>السبب</strong><span class="whitespace-pre-wrap">{{ $event->reason }}</span></p>@endif
        @include('storage.partials.audit-values', ['event' => $event, 'labels' => $labels])
    </article>
    @empty
    <section class="ui-panel empty-history"><span class="section-icon"><x-icon name="history" /></span><h3>لم تُسجّل تغييرات بعد</h3><p>ستظهر هنا العمليات الجديدة والتعديلات على هذا الصنف، مع اسم المستخدم وتفاصيل كل تغيير.</p></section>
    @endforelse
    @if($events->isNotEmpty())</div>@endif
    <div class="history-note"><x-icon name="shield" /><p>يُحفظ السجل تلقائياً ولا يمكن تعديله أو حذفه من التطبيق. العمليات السابقة لتفعيل السجل لا تظهر كتغييرات تاريخية.</p></div>
    {{ $events->links() }}
</div>
@endsection
