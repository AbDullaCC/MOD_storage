<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة المخزون</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }

        .modal-scroll::-webkit-scrollbar {
            width: 8px;
        }

        .modal-scroll::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
        }
    </style>
    @include('partials.app-styles')
</head>

<body class="app-shell">

    @include('partials.account-nav')

    <main class="app-content" data-page-content>

        <div class="page-heading">
            <div><div class="eyebrow">مساحة العمل / المخزون</div><h1>إدارة المخزون</h1><p>تابع الأصناف والكميات، وسجّل حركة المخزن بسهولة.</p></div>
            <div class="heading-actions">
                <a href="{{ route('storage.index', ['include_archived' => request()->boolean('include_archived') ? 0 : 1, 'search' => request('search')]) }}" class="ui-button"><x-icon name="archive" />{{ request()->boolean('include_archived') ? 'إخفاء المؤرشف' : 'عرض المؤرشف أيضاً' }}</a>
                <label class="ui-button cursor-pointer">
                    <input type="checkbox" id="include-out-of-stock" checked class="accent-green-600">
                    تضمين النافذ
                </label>
                <a id="export-btn" href="{{ route('storage.export', request()->only('search')) }}"
                    class="ui-button ui-button-success-soft">
                    <x-icon name="download" /> تصدير CSV
                </a>
                <a href="{{ route('storage.create') }}"
                    class="ui-button ui-button-success">
                    <x-icon name="plus" /> إضافة صنف جديد
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 mb-4 font-bold rounded shadow">✅
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 mb-4 font-bold rounded shadow">❌
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="bg-red-100 text-red-800 p-4 mb-4 rounded"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="inventory-toolbar">
            <h2>الأصناف <span id="inventory-count" class="count-badge">{{ $products->total() }} صنف</span></h2>
            <div class="search-field"><x-icon name="search" /><input type="search" id="search-input" aria-label="بحث في المخزون" value="{{ request('search') }}" placeholder="ابحث بالاسم، التصنيف أو الرقم التسلسلي..."></div>
        </div>

        <div id="results-container"
            class="ui-panel inventory-table transition-opacity duration-200">
            <table class="w-full text-right whitespace-nowrap">
                <thead>
                    <tr>
                        <th class="p-4">الصنف</th>
                        <th class="p-4">التصنيف</th>
                        <th class="p-4">المصنع</th>
                        <th class="p-4">الموديل</th>
                        <th class="p-4">التاريخ</th>
                        <th class="p-4">SN</th>
                        <th class="p-4">المخزون</th>
                        <th class="p-4">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($products as $product)
                        <tr class="hover:bg-blue-50 transition text-sm">
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <button onclick="openDetailsModal(this)"
                                        class="text-blue-500 hover:text-blue-700 bg-blue-50 p-1 rounded-full transition"
                                        title="عرض التفاصيل" data-id="{{ $product->id }}" data-name="{{ $product->name }}"
                                        data-category="{{ $product->category }}"
                                        data-manufacturer="{{ $product->manufacturer }}"
                                        data-model="{{ $product->model_type }}" data-receiver="{{ $product->reciever }}"
                                        data-date="{{ $product->added_at->format('Y-m-d\TH:i') }}"
                                        data-recorded-by="{{ $product->recorded_by_label }}"
                                        data-recorded-at="{{ $product->recorded_at_display }}"
                                        data-archived="{{ $product->archived_at ? '1' : '0' }}"
                                        data-outs="{{ $product->outs->toJson() }}" data-additions="{{ $product->additions->toJson() }}"
                                        data-desc="{{ $product->description }}" data-sn="{{ $product->serial_number }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <span class="font-bold text-gray-800">{{ $product->name }}</span>
                                    @if($product->archived_at)<span class="status-badge status-amber">مؤرشف</span>@endif
                                </div>
                            </td>
                            <td class="p-4 text-gray-600">{{ $product->category }}</td>
                            <td class="p-4 text-gray-600">{{ $product->manufacturer ?? '-' }}</td>
                            <td class="p-4 text-gray-600">{{ $product->model_type ?? '-' }}</td>
                            <td class="p-4 text-gray-600 dir-ltr">{{ $product->added_at->format('Y-m-d') }}</td>
                            <td class="p-4 font-mono text-gray-600">{{ $product->serial_number ?? '-' }}</td>
                            <td class="p-4">
                                <span
                                    class="stock-pill {{ $product->current_stock > 0 ? 'status-green' : 'status-red' }}">
                                    {{ $product->current_stock }} / {{ $product->total_in }}
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    @unless($product->archived_at)
                                    <button
                                        onclick="openAddModal({{ $product->id }}, '{{ addslashes($product->name) }}')"
                                        class="ui-button ui-button-success ui-button-small">
                                        <x-icon name="plus" /><span>إضافة</span>
                                    </button>
                                    @if($product->current_stock > 0)
                                        <button
                                            onclick="openRemoveModal({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->current_stock }})"
                                            class="ui-button ui-button-danger ui-button-small">
                                            <x-icon name="box" /><span>سحب</span>
                                        </button>
                                    @endif
                                    @endunless
                                </div>
                                @if($product->current_stock <= 0)
                                    <span class="text-gray-400 text-xs font-bold">نفذت الكمية</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if(count($products) == 0)
            <div class="p-10 text-center text-gray-500">لا توجد نتائج.</div> @endif
            <div class="p-4 dir-ltr" dir="ltr"> {{ $products->links() }}
            </div>
        </div>
    </main>

    <div id="details-modal"
        role="dialog" aria-modal="true" aria-labelledby="modal-title" tabindex="-1" class="app-dialog hidden"
        onclick="closeModalIfOutside(event, 'details-modal')">
        <div
            class="dialog-panel dialog-panel-wide modal-scroll">

            <div class="dialog-header">
                <div>
                    <div class="dialog-heading"><span class="section-icon"><x-icon name="box" /></span><div><h3 id="modal-title">...</h3><p>تفاصيل الصنف وحركات المخزون</p></div></div>
                    @can('admin')
                    <div class="details-actions">
                        <a id="item-history-link" class="ui-button ui-button-soft ui-button-small"><x-icon name="history" />سجل التغييرات</a>
                        <button id="edit-item-button" onclick="openEditItemModal()"
                            class="ui-button ui-button-warning-soft ui-button-small"><x-icon name="edit" />
                            تعديل البيانات</button>
                        <form id="delete-item-form" method="POST"
                            onsubmit="openCancelModal('item', currentItemData.id); return false;"
                            class="inline">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="ui-button ui-button-danger-soft ui-button-small"><x-icon name="archive" />
                                أرشفة العنصر</button>
                        </form>
                    </div>
                    @endcan
                </div>
                <button type="button" onclick="closeModal('details-modal')" aria-label="إغلاق التفاصيل" class="icon-button"><x-icon name="close" /></button>
            </div>

            <div class="details-grid grid grid-cols-2 md:grid-cols-4 gap-4 text-right mb-6">
                <div>
                    <p class="text-xs text-gray-500">التصنيف</p>
                    <p class="font-bold text-gray-800" id="modal-category">...</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">المصنع</p>
                    <p class="font-bold text-gray-800" id="modal-manufacturer">...</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">الموديل</p>
                    <p class="font-bold text-gray-800" id="modal-model">...</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">SN</p>
                    <p class="font-mono font-bold text-blue-600" id="modal-sn">...</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500">تم الاستلام بواسطة</p>
                    <p class="font-bold text-gray-800" id="modal-receiver">...</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500">تاريخ إضافة الصنف</p>
                    <p class="font-bold text-gray-800 dir-ltr text-right" id="modal-date">...</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500">أنشأ الصنف</p>
                    <p class="font-bold text-gray-800" id="modal-recorded-by">...</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500">وقت التسجيل ({{ config('app.timezone') }})</p>
                    <p class="font-bold text-gray-800 text-right" dir="ltr" id="modal-recorded-at">...</p>
                </div>
                <div class="col-span-2 md:col-span-4 border-t pt-2">
                    <p class="text-xs text-gray-500">وصف</p>
                    <p class="text-gray-700 text-sm" id="modal-desc">...</p>
                </div>
            </div>

            <div class="mb-6 movement-in">
                <h4 class="movement-heading"><span class="section-icon status-green"><x-icon name="plus" /></span>سجل الإضافات <span class="form-hint">دفعات جديدة</span></h4>
                <div class="movement-table">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr>
                                <th class="p-2">تاريخ العملية</th>
                                <th class="p-2">الكمية</th>
                                <th class="p-2">المصدر</th>
                                <th class="p-2">ملاحظات</th>
                                <th class="p-2">سجّلها / وقت التسجيل</th>
                                @can('admin')<th>الإجراءات</th>@endcan
                            </tr>
                        </thead>
                        <tbody id="modal-additions-body"></tbody>
                    </table>
                    <p id="modal-no-additions" class="text-center p-4 text-gray-400 hidden">لا توجد دفعات إضافية لهذا
                        العنصر.</p>
                </div>
            </div>

            <div class="movement-out">
                <h4 class="movement-heading"><span class="section-icon status-red"><x-icon name="history" /></span>سجل المسحوبات</h4>
                <div class="movement-table">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr>
                                <th class="p-2">تاريخ العملية</th>
                                <th class="p-2">الكمية</th>
                                <th class="p-2">الوجهة</th>
                                <th class="p-2">ملاحظات</th>
                                <th class="p-2">سجّلها / وقت التسجيل</th>
                                @can('admin')<th>الإجراءات</th>@endcan
                            </tr>
                        </thead>
                        <tbody id="modal-history-body"></tbody>
                    </table>
                    <p id="modal-no-history" class="text-center p-4 text-gray-400 hidden">لا توجد عمليات سحب لهذا
                        العنصر.</p>
                </div>
            </div>
        </div>
    </div>

    @include('storage.partials.operation-dialogs')
    <script>
        // 1. Base URL
        const APP_URL = "{{ url('/') }}";

        // 2. Data from Controller
        let currentItemData = {};

        function escHtml(s) {
            return (s ?? '').toString().replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c]));
        }

        // One visible dialog; closing a correction returns to the same item details.
        const modalStack = [];
        const focusableSelector = 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), summary, [tabindex="0"]';

        function openModal(id) {
            const modal = document.getElementById(id);
            const previous = modalStack[modalStack.length - 1];
            if (previous?.id === id) return;
            const trigger = document.activeElement;
            if (previous) document.getElementById(previous.id).classList.add('hidden');
            modalStack.push({ id, trigger });
            modal.classList.remove('hidden');
            document.body.classList.add('modal-open');
            modal.focus();
            document.querySelectorAll('[data-page-content]').forEach(el => el.inert = true);
        }

        function closeModal(id) {
            if (modalStack[modalStack.length - 1]?.id !== id) return;
            const closed = modalStack.pop();
            document.getElementById(id).classList.add('hidden');
            const previous = modalStack[modalStack.length - 1];
            if (previous) {
                document.getElementById(previous.id).classList.remove('hidden');
            } else {
                document.body.classList.remove('modal-open');
                document.querySelectorAll('[data-page-content]').forEach(el => el.inert = false);
            }
            if (closed.trigger?.isConnected) closed.trigger.focus();
        }

        document.addEventListener('keydown', event => {
            const current = modalStack[modalStack.length - 1];
            if (!current) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(current.id);
            } else if (event.key === 'Tab') {
                const modal = document.getElementById(current.id);
                const controls = [...modal.querySelectorAll(focusableSelector)].filter(el => el.getClientRects().length);
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && (document.activeElement === first || document.activeElement === modal)) {
                    event.preventDefault(); last?.focus();
                } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === modal)) {
                    event.preventDefault(); first?.focus();
                }
            }
        });

        function formatDates(iso) {
            const d = new Date(iso);
            const displayDate = d.toLocaleDateString('ar-EG') + ' ' + d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
            const pad = (n) => String(n).padStart(2, '0');
            const inputDate = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            return { displayDate, inputDate };
        }

        function openCancelModal(kind, id) {
            document.getElementById('cancel-form').action = `${APP_URL}/storage/${kind}/${id}`;
            document.getElementById('cancel-title').textContent = kind === 'item' ? 'أرشفة العنصر' : 'إلغاء العملية';
            document.getElementById('cancel-explanation').textContent = kind === 'item'
                ? 'يمكن أرشفة الصنف إذا كان رصيده صفراً. ستبقى جميع سجلاته متاحة.'
                : kind === 'out' ? 'ستعاد الكمية للمخزون مع الاحتفاظ بالعملية الأصلية وسجل الإلغاء.'
                : 'ستخصم الكمية من الرصيد إذا كانت متاحة، مع الاحتفاظ بالعملية الأصلية وسجل الإلغاء.';
            document.getElementById('cancel-reason').value = '';
            document.getElementById('cancel-item-name').textContent = currentItemData.name;
            const movement = kind === 'item' ? null : (kind === 'out' ? currentItemData.outs : currentItemData.additions).find(m => m.id === id);
            document.getElementById('cancel-operation').textContent = movement ? `${kind === 'out' ? 'سحب' : 'إضافة'} #${id} · ${movement.quantity} وحدة` : 'أرشفة الصنف';
            document.getElementById('cancel-submit').textContent = kind === 'item' ? 'تأكيد الأرشفة' : 'تأكيد الإلغاء';
            openModal('cancel-modal');
            document.getElementById('cancel-reason').focus();
        }

        function renderMovements(bodyId, emptyId, movements, kind) {
            document.getElementById(emptyId).classList.toggle('hidden', movements.length > 0);
            document.getElementById(bodyId).innerHTML = movements.map(m => {
                const cancelled = !!m.cancelled_at;
                const cancellation = cancelled ? `<span class="status-badge status-red mt-2">ملغاة — لا تؤثر على الرصيد الحالي</span><span class="cancellation-copy">${escHtml(m.cancelled_by_name)} · ${escHtml(formatDates(m.cancelled_at).displayDate)} · ${escHtml(m.cancellation_reason)}</span>` : '';
                let controls = '';
                @can('admin')
                if (!cancelled && !currentItemData.archived) {
                    controls = `<div class="movement-actions"><button type="button" onclick="${kind === 'out' ? 'openEditOutModal' : 'openEditAdditionModal'}(${m.id})" class="ui-button ui-button-warning-soft ui-button-small"><x-icon name="edit" />تعديل</button><button type="button" onclick="openCancelModal('${kind}', ${m.id})" class="ui-button ui-button-danger-soft ui-button-small"><x-icon name="cancel" />إلغاء العملية</button></div>`;
                }
                @endcan
                return `<tr class="border-b ${cancelled ? 'cancelled-row' : ''}">
                    <td class="p-2 text-gray-600">${escHtml(formatDates(m.date).displayDate)}</td>
                    <td class="p-2 font-bold ${cancelled ? 'line-through text-gray-500' : kind === 'out' ? 'text-red-600' : 'text-green-600'}">${kind === 'out' ? '-' : '+'}${m.quantity}</td>
                    <td class="p-2">${escHtml((kind === 'out' ? m.destination : m.source) || '-')}</td>
                    <td class="p-2 text-xs text-gray-600">${escHtml(m.note || '-')}${cancellation}</td>
                    <td class="p-2 text-xs"><span class="block font-bold">${escHtml(m.recorded_by_label)}</span><span class="block whitespace-nowrap text-gray-500" dir="ltr">${escHtml(m.recorded_at_display)}</span></td>
                    @can('admin')<td class="p-2">${controls}</td>@endcan
                </tr>`;
            }).join('');
        }

        // --- OPEN DETAILS MODAL ---
        function openDetailsModal(btn) {
            currentItemData = {
                id: btn.getAttribute('data-id'),
                name: btn.getAttribute('data-name'),
                category: btn.getAttribute('data-category'),
                manufacturer: btn.getAttribute('data-manufacturer'),
                model: btn.getAttribute('data-model'),
                sn: btn.getAttribute('data-sn'),
                receiver: btn.getAttribute('data-receiver'),
                date: btn.getAttribute('data-date'),
                desc: btn.getAttribute('data-desc'),
                recordedBy: btn.getAttribute('data-recorded-by'),
                recordedAt: btn.getAttribute('data-recorded-at'),
                archived: btn.getAttribute('data-archived') === '1',
                outs: JSON.parse(btn.getAttribute('data-outs') || '[]'),
                additions: JSON.parse(btn.getAttribute('data-additions') || '[]'),
            };

            // Fill Static Data
            document.getElementById('modal-title').innerText = currentItemData.name;
            document.getElementById('modal-category').innerText = currentItemData.category;
            document.getElementById('modal-manufacturer').innerText = currentItemData.manufacturer || '-';
            document.getElementById('modal-model').innerText = currentItemData.model || '-';
            document.getElementById('modal-receiver').innerText = currentItemData.receiver || '-';
            document.getElementById('modal-date').innerText = currentItemData.date.replace('T', ' · ');
            document.getElementById('modal-desc').innerText = currentItemData.desc || '-';
            document.getElementById('modal-sn').innerText = currentItemData.sn || '-';
            document.getElementById('modal-recorded-by').innerText = currentItemData.recordedBy;
            document.getElementById('modal-recorded-at').innerText = currentItemData.recordedAt;

            // Set Delete Action
            @can('admin')
            document.getElementById('delete-item-form').action = `${APP_URL}/storage/item/${currentItemData.id}`;
            document.getElementById('delete-item-form').classList.toggle('hidden', currentItemData.archived);
            document.getElementById('edit-item-button').classList.toggle('hidden', currentItemData.archived);
            document.getElementById('item-history-link').href = `${APP_URL}/storage/item/${currentItemData.id}/history`;
            @endcan

            renderMovements('modal-additions-body', 'modal-no-additions', currentItemData.additions, 'addition');
            renderMovements('modal-history-body', 'modal-no-history', currentItemData.outs, 'out');
            openModal('details-modal');
        }

        function openEditItemModal() {
            document.getElementById('edit-item-form').action = `${APP_URL}/storage/item/${currentItemData.id}`;
            document.getElementById('edit-name').value = currentItemData.name;
            document.getElementById('edit-category').value = currentItemData.category;
            document.getElementById('edit-manufacturer').value = currentItemData.manufacturer;
            document.getElementById('edit-model').value = currentItemData.model;
            document.getElementById('edit-sn').value = currentItemData.sn;
            document.getElementById('edit-receiver').value = currentItemData.receiver;
            document.getElementById('edit-date').value = currentItemData.date; // Ensure this is also formatted similarly if needed
            document.getElementById('edit-desc').value = currentItemData.desc;
            document.querySelector('#edit-item-form [name="reason"]').value = '';
            openModal('edit-item-modal');
        }

        function openEditOutModal(id) {
            const record = currentItemData.outs.find(m => m.id === id);
            const dest = record.destination, note = record.note, date = formatDates(record.date).inputDate;
            document.getElementById('edit-out-form').action = `${APP_URL}/storage/out/${id}`;
            document.getElementById('edit-out-destination').value = dest !== 'null' ? dest : '';
            // FIX: The 'date' passed here is now the clean 'inputDate' string
            document.getElementById('edit-out-date').value = date;
            document.getElementById('edit-out-note').value = note !== 'null' ? note : '';
            document.getElementById('edit-out-reason').value = '';
            document.getElementById('edit-out-item-name').textContent = currentItemData.name;
            document.getElementById('edit-out-quantity').textContent = `سحب #${id} · ${record.quantity} وحدة`;
            openModal('edit-out-modal');
        }

        function openEditAdditionModal(id) {
            const record = currentItemData.additions.find(m => m.id === id);
            const source = record.source, note = record.note, date = formatDates(record.date).inputDate;
            document.getElementById('edit-addition-form').action = `${APP_URL}/storage/addition/${id}`;
            document.getElementById('edit-addition-source').value = source !== 'null' ? source : '';
            document.getElementById('edit-addition-date').value = date;
            document.getElementById('edit-addition-note').value = note !== 'null' ? note : '';
            document.getElementById('edit-addition-reason').value = '';
            document.getElementById('edit-addition-item-name').textContent = currentItemData.name;
            document.getElementById('edit-addition-quantity').textContent = `إضافة #${id} · ${record.quantity} وحدة`;
            openModal('edit-addition-modal');
        }

        function openAddModal(id, name) {
            document.getElementById('add-id').value = id;
            document.getElementById('add-item-name').innerText = name;
            openModal('add-modal');
        }

        function openRemoveModal(id, name, max) {
            document.getElementById('remove-id').value = id;
            document.getElementById('remove-item-name').innerText = name;
            document.getElementById('remove-item-stock').innerText = max;
            document.getElementById('remove-qty').max = max;
            document.getElementById('remove-qty').value = 1;
            openModal('remove-modal');
        }

        function closeModalIfOutside(e, id) { if (e.target.id === id) closeModal(id); }

        // Live Search + Export link sync
        const searchInput = document.getElementById('search-input');
        const resultsContainer = document.getElementById('results-container');
        const exportBtn = document.getElementById('export-btn');
        const includeOutOfStock = document.getElementById('include-out-of-stock');
        const EXPORT_BASE = "{{ route('storage.export') }}";

        function syncExportLink(searchValue) {
            if (!exportBtn) return;
            const params = new URLSearchParams();
            const s = searchValue ?? (searchInput ? searchInput.value : '');
            if (s && s.trim() !== '') params.set('search', s.trim());
            params.set('include_out_of_stock', includeOutOfStock && includeOutOfStock.checked ? '1' : '0');
            exportBtn.href = EXPORT_BASE + '?' + params.toString();
        }

        if (includeOutOfStock) {
            includeOutOfStock.addEventListener('change', () => syncExportLink());
        }
        syncExportLink();
        let timeout = null;
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(timeout);
                resultsContainer.style.opacity = '0.5';
                syncExportLink(searchInput.value);
                timeout = setTimeout(() => {
                    const url = new URL(window.location);
                    url.searchParams.set('search', searchInput.value);
                    window.history.pushState({}, '', url);
                    fetch(url).then(r => r.text()).then(html => {
                        const page = new DOMParser().parseFromString(html, 'text/html');
                        resultsContainer.innerHTML = page.getElementById('results-container').innerHTML;
                        document.getElementById('inventory-count').textContent = page.getElementById('inventory-count').textContent;
                        resultsContainer.style.opacity = '1';
                    });
                }, 500);
            });
        }

        @if($errors->any())
            window.scrollTo({ top: 0, behavior: 'smooth' });
        @endif
    </script>
</body>

</html>
