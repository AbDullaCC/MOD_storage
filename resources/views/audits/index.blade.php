@extends('layouts.account')
@section('title', 'سجل العمليات')
@section('content')
<div class="audit-page">
    <div class="page-heading">
        <div><div class="eyebrow">المراجعة والمتابعة</div><h1>سجل العمليات</h1><p>حركات المخزون والتعديلات والإلغاءات في مكان واحد.</p></div>
        <a class="ui-button ui-button-success-soft" href="{{ route('audits.export', $filters) }}"><x-icon name="download" /> تصدير النتائج CSV</a>
    </div>
    @if($selectedProduct)
        <section class="ui-panel item-summary" aria-label="ملخص الصنف">
            <div class="summary-product"><span class="section-icon"><x-icon name="box" /></span><div><h2>{{ $selectedProduct->name }}</h2><p>{{ $selectedProduct->category }} · <span class="status-badge {{ $selectedProduct->archived_at ? 'status-amber' : 'status-green' }}">{{ $selectedProduct->archived_at ? 'مؤرشف' : 'نشط' }}</span></p></div></div>
            <div class="summary-stat"><span>الرصيد الحالي</span><strong>{{ $selectedProduct->current_stock }}</strong><small>وحدة</small></div>
            <a class="ui-button" href="{{ route('audits.index', collect($filters)->except('product_in_id')->all()) }}">عرض كل الأصناف</a>
        </section>
        @if($selectedProduct->archived_at)
            <section class="ui-panel restore-panel">
                <div class="panel-heading"><span class="section-icon status-amber"><x-icon name="archive" /></span><div><h2>هذا الصنف مؤرشف</h2><p>أعد تفعيله لإجراء عمليات جديدة عليه. يبقى سجله السابق محفوظاً.</p></div></div>
                <form method="POST" action="{{ route('storage.restoreItem', $selectedProduct->id) }}">@csrf
                    <label for="restore-reason">سبب إعادة التفعيل<input id="restore-reason" name="reason" required minlength="3" maxlength="1000" placeholder="وضّح سبب إعادة الصنف للمخزون" value="{{ old('reason') }}"></label>
                    <button class="ui-button ui-button-success"><x-icon name="history" /> إعادة تفعيل الصنف</button>
                </form>
            </section>
        @endif
    @endif
    <details class="ui-panel audit-filter-panel" @if(count(array_filter($filters, static fn ($value) => $value !== null && $value !== ''))) open @endif>
        <summary class="audit-filter-toggle"><span><x-icon name="search" /> تصفية السجل</span><x-icon name="chevron" /></summary>
        <form method="GET" action="{{ route('audits.index') }}">
            <div class="audit-filters">
                <label for="audit-user">المستخدم<select id="audit-user" name="actor_id"><option value="">كل المستخدمين</option><option value="unknown" @selected(($filters['actor_id'] ?? '') === 'unknown')>المستخدم غير معروف</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['actor_id'] ?? '') === (string) $user->id)>{{ $user->name }} ({{ $user->username }}){{ $user->is_active ? '' : ' — معطّل' }}</option>@endforeach</select></label>
                <label for="audit-product">الصنف<select id="audit-product" name="product_in_id"><option value="">كل الأصناف</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) ($filters['product_in_id'] ?? '') === (string) $product->id)>{{ $product->name }} — #{{ $product->id }}{{ $product->archived_at ? ' — مؤرشف' : '' }}</option>@endforeach</select></label>
                <label for="audit-type">نوع العملية<select id="audit-type" name="record_type"><option value="">كل الأنواع</option>@foreach(\App\Models\InventoryAudit::TYPES as $value => $label)<option value="{{ $value }}" @selected(($filters['record_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label for="audit-action">الإجراء<select id="audit-action" name="action"><option value="">كل الإجراءات</option>@foreach(\App\Models\InventoryAudit::ACTIONS as $value => $label)<option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label for="audit-from">من تاريخ التسجيل<input id="audit-from" name="from" type="date" value="{{ ($filters['from'] ?? '') }}"></label>
                <label for="audit-to">إلى تاريخ التسجيل<input id="audit-to" name="to" type="date" value="{{ ($filters['to'] ?? '') }}"></label>
            </div>
            <div class="audit-filter-footer"><span class="form-hint"><x-icon name="clock" /> يشمل يوم البداية والنهاية · <bdi>{{ config('app.timezone') }}</bdi></span><div class="heading-actions"><a class="ui-button" href="{{ route('audits.index') }}">مسح الفلاتر</a><button class="ui-button ui-button-primary" type="submit"><x-icon name="search" /> عرض النتائج</button></div></div>
        </form>
    </details>
    <div class="history-section-heading"><div><h2>النتائج <span class="count-badge">{{ $events->total() }} سجل</span></h2><p>الأحدث أولاً · افتح التفاصيل عند الحاجة.</p></div>@if($events->total())<span class="form-hint">عرض {{ $events->firstItem() }}–{{ $events->lastItem() }} من {{ $events->total() }}</span>@endif</div>
    <p id="item-card-message" role="status" class="form-hint mb-4" hidden></p>
    @if($events->isNotEmpty())
        <div class="ui-panel operations-panel">
            <table class="operations-table" aria-label="سجل العمليات">
                <colgroup><col class="operation-action-col"><col class="operation-item-col"><col class="operation-stock-col"><col class="operation-user-col"><col class="operation-date-col"><col class="operation-details-col"></colgroup>
                <thead><tr><th scope="col">ماذا حدث؟</th><th scope="col">الصنف</th><th scope="col">الرصيد قبل ← بعد</th><th scope="col">بواسطة</th><th scope="col">وقت التسجيل</th><th scope="col"><span class="sr-only">التفاصيل</span></th></tr></thead>
                @foreach($events as $event)
                    @include('audits.partials.operation-row', ['event' => $event])
                @endforeach
            </table>
        </div>
    @else
        <section class="ui-panel empty-history"><span class="section-icon"><x-icon name="search" /></span><h3>لا توجد عمليات مطابقة</h3><p>غيّر الفلاتر أو امسحها لعرض بقية السجل. ستظهر العمليات الجديدة تلقائياً بعد تسجيلها.</p></section>
    @endif
    {{ $events->links() }}
    <div class="history-note"><x-icon name="shield" /><p>التفاصيل الجديدة محفوظة عند التسجيل. السجلات السابقة تعرض بيانات العملية الحالية دون افتراض رصيد تاريخي أو مستخدم غير مسجّل. أسماء الأصناف في الروابط هي أسماؤها الحالية.</p></div>
</div>
@endsection
@section('dialogs')
@include('storage.partials.item-card')
@include('storage.partials.operation-dialogs')
@include('storage.partials.inventory-dialog-scripts')
<script>
document.querySelectorAll('[data-operation-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const row = document.getElementById(button.getAttribute('aria-controls'));
        const expanded = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(expanded));
        row.hidden = !expanded;
    });
});
const itemMessage = document.getElementById('item-card-message');
let itemRequest;
document.querySelectorAll('[data-item-card]').forEach(link => {
    link.addEventListener('click', async event => {
        event.preventDefault();
        itemRequest?.abort();
        const request = new AbortController();
        itemRequest = request;
        link.setAttribute('aria-busy', 'true');
        itemMessage.hidden = false;
        itemMessage.textContent = 'جارٍ تحميل بيانات الصنف…';
        try {
            const response = await fetch(link.href, {signal: request.signal, headers: {'Accept': 'application/json'}});
            if (!response.ok || response.redirected) throw new Error('Unable to load item');
            const data = await response.json();
            if (!request.signal.aborted) {
                itemMessage.hidden = true;
                link.focus();
                showItemDetails(data);
            }
        } catch (error) {
            if (!request.signal.aborted) itemMessage.textContent = 'تعذّر تحميل بيانات الصنف. حاول مرة أخرى.';
        } finally {
            link.removeAttribute('aria-busy');
        }
    });
});
</script>
@endsection
