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
</head>

<body class="bg-gray-100 p-4 md:p-10">

    @include('partials.account-nav')

    <div class="max-w-[98%] mx-auto">

        <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
            <h1 class="text-3xl font-bold text-gray-800">📦 نظام إدارة المخزون</h1>
            <div class="flex items-center gap-2">
                <label class="bg-white border px-3 py-2 rounded-lg shadow text-sm font-bold text-gray-700 flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="include-out-of-stock" checked class="accent-green-600">
                    تضمين النافذ
                </label>
                <a id="export-btn" href="{{ route('storage.export', request()->only('search')) }}"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 shadow flex items-center gap-2 transition font-bold">
                    📥 تصدير CSV
                </a>
                <a href="{{ route('storage.report') }}"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 shadow flex items-center gap-2 transition font-bold">
                    📄 التقارير
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

        <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-blue-500">
            <h2 class="text-xl font-bold mb-4 text-blue-900">📥 إدخال للمخزن (إضافة)</h2>
            <form action="{{ route('storage.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @csrf
                <input type="text" name="name" placeholder="اسم الصنف"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>
                <input type="text" name="category" placeholder="التصنيف"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>
                <input type="text" name="manufacturer" placeholder="الشركة المصنعة"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                <input type="text" name="model_type" placeholder="الموديل / النوع"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                <input type="number" name="quantity" placeholder="الكمية"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>
                <input type="text" name="serial_number" placeholder="الرقم التسلسلي"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                <input type="text" name="reciever" placeholder="اسم المستلم (اختياري)"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                <input type="datetime-local" name="added_at" value="{{ now()->format('Y-m-d\TH:i') }}"
                    max="{{ now()->format('Y-m-d\TH:i') }}"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none text-right" required>
                <textarea name="description" placeholder="ملاحظات..." rows="1"
                    class="col-span-1 md:col-span-4 border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none"></textarea>
                <button type="submit"
                    class="col-span-1 md:col-span-4 bg-blue-600 text-white py-2 rounded font-bold hover:bg-blue-700 shadow transition">+
                    إضافة للمخزون</button>
            </form>
        </div>

        <div class="mb-6 relative">
            <input type="text" id="search-input" placeholder="🔍 بحث سريع..."
                class="w-full border p-3 rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>

        <div id="results-container"
            class="bg-white shadow-lg rounded-lg overflow-x-auto transition-opacity duration-200">
            <table class="w-full text-right whitespace-nowrap">
                <thead class="bg-gray-200 text-gray-700 text-sm">
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
                                </div>
                            </td>
                            <td class="p-4 text-gray-600">{{ $product->category }}</td>
                            <td class="p-4 text-gray-600">{{ $product->manufacturer ?? '-' }}</td>
                            <td class="p-4 text-gray-600">{{ $product->model_type ?? '-' }}</td>
                            <td class="p-4 text-gray-600 dir-ltr">{{ $product->added_at->format('Y-m-d') }}</td>
                            <td class="p-4 font-mono text-gray-600">{{ $product->serial_number ?? '-' }}</td>
                            <td class="p-4">
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-bold {{ $product->current_stock > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $product->current_stock }} / {{ $product->total_in }}
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-1">
                                    <button
                                        onclick="openAddModal({{ $product->id }}, '{{ addslashes($product->name) }}')"
                                        class="bg-green-500 text-white px-3 py-1 rounded text-xs hover:bg-green-600 transition shadow flex items-center gap-1">
                                        <span>إضافة</span>
                                    </button>
                                    @if($product->current_stock > 0)
                                        <button
                                            onclick="openRemoveModal({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->current_stock }})"
                                            class="bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600 transition shadow flex items-center gap-1">
                                            <span>سحب</span>
                                        </button>
                                    @endif
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
    </div>

    <div id="details-modal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex justify-center items-center backdrop-blur-sm"
        onclick="closeModalIfOutside(event, 'details-modal')">
        <div
            class="bg-white rounded-lg shadow-2xl w-full max-w-5xl p-6 relative max-h-[90vh] overflow-y-auto modal-scroll">

            <div class="flex justify-between items-start border-b pb-4 mb-4">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800" id="modal-title">...</h3>
                    @can('admin')
                    <div class="flex gap-2 mt-2">
                        <button onclick="openEditItemModal()"
                            class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm hover:bg-blue-200 font-bold transition">✏️
                            تعديل البيانات</button>
                        <form id="delete-item-form" method="POST"
                            onsubmit="return confirm('هل أنت متأكد من حذف هذا العنصر؟ سيتم حذف جميع سجلات السحب المرتبطة به أيضاً!')"
                            class="inline">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="bg-red-100 text-red-700 px-3 py-1 rounded text-sm hover:bg-red-200 font-bold transition">🗑️
                                حذف العنصر</button>
                        </form>
                    </div>
                    @endcan
                </div>
                <button onclick="document.getElementById('details-modal').classList.add('hidden')"
                    class="text-gray-400 hover:text-red-500 text-3xl">&times;</button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-right mb-6 bg-gray-50 p-4 rounded-lg">
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
                    <p class="text-xs text-gray-500">تاريخ العملية (الرصيد الافتتاحي)</p>
                    <p class="font-bold text-gray-800 dir-ltr text-right" id="modal-date">...</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500">سجّل الرصيد الافتتاحي</p>
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

            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-800 mb-2 border-r-4 border-green-500 pr-2">📥 سجل الإضافات (دفعات جديدة)</h4>
                <div class="bg-white border rounded overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-100 text-gray-600">
                            <tr>
                                <th class="p-2">تاريخ العملية</th>
                                <th class="p-2">الكمية</th>
                                <th class="p-2">المصدر</th>
                                <th class="p-2">ملاحظات</th>
                                <th class="p-2">سجّلها / وقت التسجيل</th>
                                @can('admin')<th class="p-2 w-20">تحكم</th>@endcan
                            </tr>
                        </thead>
                        <tbody id="modal-additions-body"></tbody>
                    </table>
                    <p id="modal-no-additions" class="text-center p-4 text-gray-400 hidden">لا توجد دفعات إضافية لهذا
                        العنصر.</p>
                </div>
            </div>

            <div>
                <h4 class="text-lg font-bold text-gray-800 mb-2 border-r-4 border-blue-500 pr-2">📜 سجل المسحوبات</h4>
                <div class="bg-white border rounded overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-100 text-gray-600">
                            <tr>
                                <th class="p-2">تاريخ العملية</th>
                                <th class="p-2">الكمية</th>
                                <th class="p-2">الوجهة</th>
                                <th class="p-2">ملاحظات</th>
                                <th class="p-2">سجّلها / وقت التسجيل</th>
                                @can('admin')<th class="p-2 w-20">تحكم</th>@endcan
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

    <div id="edit-item-modal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden z-[60] flex justify-center items-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl p-6 relative">
            <h3 class="text-xl font-bold mb-4 text-blue-900">✏️ تعديل بيانات العنصر</h3>
            <form id="edit-item-form" method="POST" class="grid grid-cols-2 gap-4">
                @csrf @method('PUT')
                <input type="text" name="name" id="edit-name" class="border p-2 rounded" required placeholder="الاسم">
                <input type="text" name="category" id="edit-category" class="border p-2 rounded" required
                    placeholder="التصنيف">
                <input type="text" name="manufacturer" id="edit-manufacturer" class="border p-2 rounded"
                    placeholder="المصنع">
                <input type="text" name="model_type" id="edit-model" class="border p-2 rounded" placeholder="الموديل">
                <input type="text" name="serial_number" id="edit-sn" class="border p-2 rounded" placeholder="SN">
                <input type="text" name="reciever" id="edit-receiver" class="border p-2 rounded" placeholder="المستلم">
                <input type="datetime-local" name="added_at" id="edit-date" class="border p-2 rounded text-right"
                    required max="{{ now()->format('Y-m-d\TH:i') }}">
                <textarea name="description" id="edit-desc" class="col-span-2 border p-2 rounded" rows="2"
                    placeholder="وصف"></textarea>

                <div class="col-span-2 flex gap-2 mt-4">
                    <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 flex-1">حفظ
                        التعديلات</button>
                    <button type="button" onclick="document.getElementById('edit-item-modal').classList.add('hidden')"
                        class="bg-gray-200 px-6 py-2 rounded font-bold hover:bg-gray-300">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-out-modal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden z-[60] flex justify-center items-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6 relative border-t-4 border-yellow-500">
            <h3 class="text-xl font-bold mb-4">✏️ تعديل عملية السحب</h3>
            <form id="edit-out-form" method="POST" class="space-y-3">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-bold text-gray-500">الوجهة</label>
                    <input type="text" name="destination" id="edit-out-destination" class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">التاريخ</label>
                    <input type="datetime-local" name="date" id="edit-out-date"
                        class="w-full border p-2 rounded text-right" required max="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">ملاحظات</label>
                    <textarea name="note" id="edit-out-note" class="w-full border p-2 rounded"></textarea>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="submit"
                        class="bg-yellow-500 text-white px-6 py-2 rounded font-bold hover:bg-yellow-600 flex-1">تحديث</button>
                    <button type="button" onclick="document.getElementById('edit-out-modal').classList.add('hidden')"
                        class="bg-gray-200 px-6 py-2 rounded font-bold">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <div id="remove-modal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex justify-center items-center backdrop-blur-sm"
        onclick="closeModalIfOutside(event, 'remove-modal')">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6 relative border-t-4 border-red-500">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">سحب من المخزن</h3>
                <p class="text-sm text-gray-500">العنصر: <span id="remove-item-name"
                        class="font-bold text-black">...</span></p>
                <p class="text-xs text-gray-400">المتوفر: <span id="remove-item-stock">0</span></p>
            </div>
            <form action="{{ route('storage.out') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="product_in_id" id="remove-id">
                <input type="number" name="quantity" id="remove-qty" min="1"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300" required
                    placeholder="الكمية">
                <input type="text" name="destination"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300"
                    placeholder="الوجهة (اختياري)">
                <input type="datetime-local" name="date" value="{{ now()->format('Y-m-d\TH:i') }}"
                    max="{{ now()->format('Y-m-d\TH:i') }}"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300 text-right" required>
                <textarea name="note" rows="2"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300"
                    placeholder="ملاحظات"></textarea>
                <div class="mt-6 flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-red-600 text-white py-2 rounded font-bold hover:bg-red-700 transition">تأكيد
                        السحب</button>
                    <button type="button" onclick="document.getElementById('remove-modal').classList.add('hidden')"
                        class="px-4 py-2 bg-gray-200 rounded font-bold hover:bg-gray-300">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <div id="add-modal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex justify-center items-center backdrop-blur-sm"
        onclick="closeModalIfOutside(event, 'add-modal')">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6 relative border-t-4 border-green-500">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-green-600">إضافة كمية (دفعة جديدة)</h3>
                <p class="text-sm text-gray-500">العنصر: <span id="add-item-name"
                        class="font-bold text-black">...</span></p>
            </div>
            <form action="{{ route('storage.addition') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="product_in_id" id="add-id">
                <input type="number" name="quantity" min="1"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-green-300" required
                    placeholder="الكمية المضافة" value="1">
                <input type="text" name="source"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-green-300"
                    placeholder="المصدر (اختياري)">
                <input type="datetime-local" name="date" value="{{ now()->format('Y-m-d\TH:i') }}"
                    max="{{ now()->format('Y-m-d\TH:i') }}"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-green-300 text-right" required>
                <textarea name="note" rows="2"
                    class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-green-300"
                    placeholder="ملاحظات"></textarea>
                <div class="mt-6 flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-green-600 text-white py-2 rounded font-bold hover:bg-green-700 transition">تأكيد
                        الإضافة</button>
                    <button type="button" onclick="document.getElementById('add-modal').classList.add('hidden')"
                        class="px-4 py-2 bg-gray-200 rounded font-bold hover:bg-gray-300">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-addition-modal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden z-[60] flex justify-center items-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6 relative border-t-4 border-yellow-500">
            <h3 class="text-xl font-bold mb-4">✏️ تعديل عملية الإضافة</h3>
            <form id="edit-addition-form" method="POST" class="space-y-3">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-bold text-gray-500">المصدر</label>
                    <input type="text" name="source" id="edit-addition-source" class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">التاريخ</label>
                    <input type="datetime-local" name="date" id="edit-addition-date"
                        class="w-full border p-2 rounded text-right" required max="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">ملاحظات</label>
                    <textarea name="note" id="edit-addition-note" class="w-full border p-2 rounded"></textarea>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="submit"
                        class="bg-yellow-500 text-white px-6 py-2 rounded font-bold hover:bg-yellow-600 flex-1">تحديث</button>
                    <button type="button" onclick="document.getElementById('edit-addition-modal').classList.add('hidden')"
                        class="bg-gray-200 px-6 py-2 rounded font-bold">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 1. Base URL
        const APP_URL = "{{ url('/') }}";

        // 2. Data from Controller
        const historyData = @json($products->mapWithKeys(fn($i) => [$i->id => $i->outs]));
        const additionsData = @json($products->mapWithKeys(fn($i) => [$i->id => $i->additions]));
        let currentItemData = {};

        function escHtml(s) {
            return (s ?? '').toString().replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c]));
        }
        function escJs(s) { return escHtml((s ?? '').toString().replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/[\r\n]/g, ' ')); }

        function formatDates(iso) {
            const d = new Date(iso);
            const displayDate = d.toLocaleDateString('ar-EG') + ' ' + d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
            const pad = (n) => String(n).padStart(2, '0');
            const inputDate = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            return { displayDate, inputDate };
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
            };

            // Fill Static Data
            document.getElementById('modal-title').innerText = currentItemData.name;
            document.getElementById('modal-category').innerText = currentItemData.category;
            document.getElementById('modal-manufacturer').innerText = currentItemData.manufacturer || '-';
            document.getElementById('modal-model').innerText = currentItemData.model || '-';
            document.getElementById('modal-receiver').innerText = currentItemData.receiver || '-';
            document.getElementById('modal-date').innerText = currentItemData.date;
            document.getElementById('modal-desc').innerText = currentItemData.desc || '-';
            document.getElementById('modal-sn').innerText = currentItemData.sn || '-';
            document.getElementById('modal-recorded-by').innerText = currentItemData.recordedBy;
            document.getElementById('modal-recorded-at').innerText = currentItemData.recordedAt;

            // Set Delete Action
            @can('admin')
            document.getElementById('delete-item-form').action = `${APP_URL}/storage/item/${currentItemData.id}`;
            @endcan

            // Build Additions Table
            const additionsTbody = document.getElementById('modal-additions-body');
            additionsTbody.innerHTML = '';
            const additions = additionsData[currentItemData.id] || [];

            if (additions.length > 0) {
                document.getElementById('modal-no-additions').classList.add('hidden');
                additions.forEach(add => {
                    const { displayDate, inputDate } = formatDates(add.date);

                    const row = `
                    <tr class="border-b border-gray-100 hover:bg-white group">
                        <td class="p-2 text-gray-600 dir-ltr text-right">${displayDate}</td>
                        <td class="p-2 font-bold text-green-600">+${add.quantity}</td>
                        <td class="p-2 text-gray-800">${escHtml(add.source || '-')}</td>
                        <td class="p-2 text-gray-500 text-xs">${escHtml(add.note || '-')}</td>
                        <td class="p-2 text-xs"><span class="block font-bold">${escHtml(add.recorded_by_label)}</span><span class="block whitespace-nowrap text-gray-500" dir="ltr">${escHtml(add.recorded_at_display)}</span></td>
                        @can('admin')
                        <td class="p-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="openEditAdditionModal(${add.id}, '${escJs(add.source)}', '${inputDate}', '${escJs(add.note)}')" class="text-blue-500 hover:bg-blue-100 p-1 rounded" title="تعديل">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>

                            <form action="${APP_URL}/storage/addition/${add.id}" method="POST" onsubmit="return confirm('هل تريد حذف عملية الإضافة هذه؟')">
                                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="text-red-500 hover:bg-red-100 p-1 rounded" title="حذف الإضافة"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                            </form>
                        </td>
                        @endcan
                    </tr>`;
                    additionsTbody.innerHTML += row;
                });
            } else {
                document.getElementById('modal-no-additions').classList.remove('hidden');
            }

            // Build History Table
            const tbody = document.getElementById('modal-history-body');
            tbody.innerHTML = '';
            const outs = historyData[currentItemData.id] || [];

            if (outs.length > 0) {
                document.getElementById('modal-no-history').classList.add('hidden');
                outs.forEach(out => {
                    const { displayDate, inputDate } = formatDates(out.date);

                    const row = `
                    <tr class="border-b border-gray-100 hover:bg-white group">
                        <td class="p-2 text-gray-600 dir-ltr text-right">${displayDate}</td>
                        <td class="p-2 font-bold text-red-600">-${out.quantity}</td>
                        <td class="p-2 text-gray-800">${escHtml(out.destination || '-')}</td>
                        <td class="p-2 text-gray-500 text-xs">${escHtml(out.note || '-')}</td>
                        <td class="p-2 text-xs"><span class="block font-bold">${escHtml(out.recorded_by_label)}</span><span class="block whitespace-nowrap text-gray-500" dir="ltr">${escHtml(out.recorded_at_display)}</span></td>
                        @can('admin')
                        <td class="p-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="openEditOutModal(${out.id}, '${escJs(out.destination)}', '${inputDate}', '${escJs(out.note)}')" class="text-blue-500 hover:bg-blue-100 p-1 rounded" title="تعديل">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                            
                            <form action="${APP_URL}/storage/out/${out.id}" method="POST" onsubmit="return confirm('هل تريد استرجاع هذه الكمية للمخزن؟')">
                                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="text-red-500 hover:bg-red-100 p-1 rounded" title="إلغاء السحب"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                            </form>
                        </td>
                        @endcan
                    </tr>`;
                    tbody.innerHTML += row;
                });
            } else {
                document.getElementById('modal-no-history').classList.remove('hidden');
            }
            document.getElementById('details-modal').classList.remove('hidden');
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
            document.getElementById('edit-item-modal').classList.remove('hidden');
        }

        function openEditOutModal(id, dest, date, note) {
            document.getElementById('edit-out-form').action = `${APP_URL}/storage/out/${id}`;
            document.getElementById('edit-out-destination').value = dest !== 'null' ? dest : '';
            // FIX: The 'date' passed here is now the clean 'inputDate' string
            document.getElementById('edit-out-date').value = date;
            document.getElementById('edit-out-note').value = note !== 'null' ? note : '';
            document.getElementById('edit-out-modal').classList.remove('hidden');
        }

        function openEditAdditionModal(id, source, date, note) {
            document.getElementById('edit-addition-form').action = `${APP_URL}/storage/addition/${id}`;
            document.getElementById('edit-addition-source').value = source !== 'null' ? source : '';
            document.getElementById('edit-addition-date').value = date;
            document.getElementById('edit-addition-note').value = note !== 'null' ? note : '';
            document.getElementById('edit-addition-modal').classList.remove('hidden');
        }

        function openAddModal(id, name) {
            document.getElementById('add-id').value = id;
            document.getElementById('add-item-name').innerText = name;
            document.getElementById('add-modal').classList.remove('hidden');
        }

        function openRemoveModal(id, name, max) {
            document.getElementById('remove-id').value = id;
            document.getElementById('remove-item-name').innerText = name;
            document.getElementById('remove-item-stock').innerText = max;
            document.getElementById('remove-qty').max = max;
            document.getElementById('remove-qty').value = 1;
            document.getElementById('remove-modal').classList.remove('hidden');
        }

        function closeModalIfOutside(e, id) { if (e.target.id === id) document.getElementById(id).classList.add('hidden'); }

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
                        resultsContainer.innerHTML = new DOMParser().parseFromString(html, 'text/html').getElementById('results-container').innerHTML;
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
