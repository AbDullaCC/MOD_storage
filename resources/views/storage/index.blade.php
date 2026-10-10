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
                @can('admin')
                <a id="export-btn" href="{{ route('storage.export', request()->only('search')) }}"
                    class="ui-button ui-button-success-soft">
                    <x-icon name="download" /> تصدير CSV
                </a>
                @endcan
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
                                        data-category="{{ $product->category }}" data-stock="{{ $product->current_stock }}"
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
                                    {{ $product->current_stock }}
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
            <div class="p-4"> {{ $products->links() }}
            </div>
        </div>
    </main>

    @include('storage.partials.item-card')
    @include('storage.partials.operation-dialogs')
    @include('storage.partials.inventory-dialog-scripts')
    <script>
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
