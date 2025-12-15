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
</head>

<body class="bg-gray-100 p-10">

    <div class="max-w-[95%] mx-auto">
        <div class="flex justify-between items-center mb-5">
            <h1 class="text-3xl font-bold text-gray-800">📦 نظام إدارة المخزون</h1>
            <a href="{{ route('storage.report') }}"
                class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 shadow flex items-center gap-2 transition">
                <span>📄 التقارير والسجلات</span>
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <strong class="font-bold">نجاح!</strong> <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <strong class="font-bold">خطأ!</strong> <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
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

                <textarea name="description" placeholder="ملاحظات..." rows="2"
                    class="col-span-1 md:col-span-4 border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none"></textarea>

                <button type="submit"
                    class="col-span-1 md:col-span-4 bg-blue-600 text-white p-2 rounded font-bold hover:bg-blue-700 transition shadow-lg">+
                    إضافة للمخزون</button>
            </form>
        </div>

        <form method="GET" class="mb-6" id="search-form">
            <div class="relative text-gray-600 focus-within:text-gray-400">
                <input type="text" name="search" id="search-input" placeholder="بحث..."
                    class="w-full border p-3 rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-blue-400"
                    value="{{ request('search') }}">
            </div>
        </form>

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
                        <th class="p-4">الرقم التسلسلي</th>
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
                                        class="text-blue-500 hover:text-blue-700 bg-blue-50 p-1 rounded-full"
                                        title="عرض التفاصيل" data-id="{{ $product->id }}" data-name="{{ $product->name }}"
                                        data-category="{{ $product->category }}"
                                        data-manufacturer="{{ $product->manufacturer ?? 'غير محدد' }}"
                                        data-model="{{ $product->model_type ?? 'غير محدد' }}"
                                        data-receiver="{{ $product->reciever }}"
                                        data-date="{{ $product->added_at->format('Y-m-d H:i') }}"
                                        data-desc="{{ $product->description ?? 'لا يوجد وصف' }}"
                                        data-sn="{{ $product->serial_number ?? 'لا يوجد' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                    <span class="font-bold text-gray-800">{{ $product->name }}</span>
                                </div>
                            </td>

                            <td class="p-4 text-gray-600">{{ $product->category }}</td>

                            <td class="p-4 text-gray-600">{{ $product->manufacturer ?? '-' }}</td>

                            <td class="p-4 text-gray-600">{{ $product->model_type ?? '-' }}</td>

                            <td class="p-4 text-gray-600 dir-ltr">
                                {{ date('Y-m-d', strtotime($product->added_at)) }}
                            </td>

                            <td class="p-4 font-mono text-gray-600">{{ $product->serial_number ?? '-' }}</td>

                            <td class="p-4">
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-bold {{ $product->current_stock > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $product->current_stock }} / {{ $product->quantity }}
                                </span>
                            </td>

                            <td class="p-4">
                                @if($product->current_stock > 0)
                                    <button
                                        onclick="openRemoveModal({{ $product->id }}, '{{ $product->name }}', {{ $product->current_stock }})"
                                        class="bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600 transition shadow flex items-center gap-1">
                                        <span>سحب</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25V9m.5-6.75h9M12 18v-5.25m0 0v-5.25m0 5.25H6.75" />
                                        </svg>
                                    </button>
                                @else
                                    <span class="text-gray-400 text-xs font-bold">نفذت الكمية</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if(count($products) == 0)
                <div class="p-10 text-center text-gray-500">لا توجد عناصر مطابقة للبحث.</div>
            @endif
        </div>
    </div>

    <div id="details-modal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex justify-center items-center backdrop-blur-sm"
        onclick="closeDetailsModal(event)">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto">
            <div class="border-b pb-3 mb-4 flex justify-between items-center">
                <h3 class="text-2xl font-bold text-gray-800" id="modal-title">...</h3>
                <button onclick="document.getElementById('details-modal').classList.add('hidden')"
                    class="text-gray-500 hover:text-red-500 text-2xl">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-4 text-right mb-6">
                <div>
                    <p class="text-sm text-gray-500">التصنيف</p>
                    <p class="font-bold text-gray-800" id="modal-category">...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">الشركة المصنعة</p>
                    <p class="font-bold text-gray-800" id="modal-manufacturer">...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">الموديل</p>
                    <p class="font-bold text-gray-800" id="modal-model">...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">الرقم التسلسلي</p>
                    <p class="font-mono font-bold text-blue-600" id="modal-sn">...</p>
                </div>
                <div class="col-span-2 border-t pt-2">
                    <p class="text-sm text-gray-500">تم الاستلام بواسطة</p>
                    <p class="font-bold text-gray-800" id="modal-receiver">...</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">تاريخ الإضافة</p>
                    <p class="font-bold text-gray-800" id="modal-date">...</p>
                </div>
                <div class="col-span-2 bg-gray-50 p-3 rounded">
                    <p class="text-sm text-gray-500">الوصف</p>
                    <p class="text-gray-700" id="modal-desc">...</p>
                </div>
            </div>

            <div class="border-t pt-4">
                <h4 class="text-lg font-bold text-gray-800 mb-2">📜 سجل المسحوبات (التاريخ)</h4>
                <div class="bg-gray-50 rounded border overflow-hidden">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-200 text-gray-700">
                            <tr>
                                <th class="p-2">التاريخ</th>
                                <th class="p-2">الكمية</th>
                                <th class="p-2">الوجهة</th>
                                <th class="p-2">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody id="modal-history-body">
                        </tbody>
                    </table>
                    <p id="modal-no-history" class="text-center p-4 text-gray-500 hidden">لا توجد عمليات سحب لهذا
                        العنصر.</p>
                </div>
            </div>

        </div>
    </div>

    <div id="remove-modal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex justify-center items-center backdrop-blur-sm"
        onclick="closeRemoveModal(event)">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6 relative border-t-4 border-red-500">
            <div class="mb-4">
                <h3 class="text-xl font-bold text-red-600">سحب من المخزن</h3>
                <p class="text-sm text-gray-500">أنت تقوم بسحب: <span id="remove-item-name"
                        class="font-bold text-black">...</span></p>
                <p class="text-xs text-gray-400">المتوفر حالياً: <span id="remove-item-stock">0</span></p>
            </div>
            <form action="{{ route('storage.out') }}" method="POST">
                @csrf
                <input type="hidden" name="product_in_id" id="remove-id">
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-bold text-gray-700">الكمية المراد سحبها</label>
                        <input type="number" name="quantity" id="remove-qty" min="1"
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700">الوجهة (اختياري)</label>
                        <input type="text" name="destination"
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700">تاريخ السحب</label>
                        <input type="datetime-local" name="date" value="{{ now()->format('Y-m-d\TH:i') }}"
                            max="{{ now()->format('Y-m-d\TH:i') }}"
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300 text-right"
                            required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700">ملاحظات</label>
                        <textarea name="note" rows="2"
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-red-300"></textarea>
                    </div>
                </div>
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

    <script>
        // 1. Pass the History Data from Laravel to JS
        // We create a Map: Product ID -> Array of Outs
        const historyData = @json($products->mapWithKeys(function ($item) {
            return [$item->id => $item->outs];
        }));

        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById('search-input');
            const resultsContainer = document.getElementById('results-container');
            let timeout = null;
            if (searchInput && resultsContainer) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(timeout);
                    resultsContainer.style.opacity = '0.5';
                    timeout = setTimeout(() => {
                        const url = new URL(window.location);
                        url.searchParams.set('search', searchInput.value);
                        window.history.pushState({}, '', url);
                        fetch(url).then(r => r.text()).then(html => {
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            const newContent = doc.getElementById('results-container');
                            if (newContent) resultsContainer.innerHTML = newContent.innerHTML;
                            resultsContainer.style.opacity = '1';
                        });
                    }, 500);
                });
            }
        });

        // --- UPDATED DETAILS MODAL LOGIC ---
        function openDetailsModal(button) {
            // 1. Fill Static Data
            document.getElementById('modal-title').innerText = button.getAttribute('data-name');
            document.getElementById('modal-category').innerText = button.getAttribute('data-category');
            document.getElementById('modal-manufacturer').innerText = button.getAttribute('data-manufacturer');
            document.getElementById('modal-model').innerText = button.getAttribute('data-model');
            document.getElementById('modal-receiver').innerText = button.getAttribute('data-receiver');
            document.getElementById('modal-date').innerText = button.getAttribute('data-date');
            document.getElementById('modal-desc').innerText = button.getAttribute('data-desc');
            document.getElementById('modal-sn').innerText = button.getAttribute('data-sn');

            // 2. Build History Table
            const productId = button.getAttribute('data-id');
            const transactions = historyData[productId] || [];
            const tbody = document.getElementById('modal-history-body');
            const noHistoryMsg = document.getElementById('modal-no-history');

            tbody.innerHTML = ''; // Clear previous data

            if (transactions.length > 0) {
                noHistoryMsg.classList.add('hidden');
                transactions.forEach(trans => {
                    // Format Date (assuming ISO string from DB)
                    const dateObj = new Date(trans.date);
                    const dateStr = dateObj.toLocaleDateString('ar-EG') + ' ' + dateObj.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });

                    const row = `
                    <tr class="border-b border-gray-100 hover:bg-white">
                        <td class="p-2 text-gray-600 dir-ltr text-right">${dateStr}</td>
                        <td class="p-2 font-bold text-red-600">-${trans.quantity}</td>
                        <td class="p-2 text-gray-800">${trans.destination}</td>
                        <td class="p-2 text-gray-500 text-xs">${trans.note || '-'}</td>
                    </tr>
                `;
                    tbody.innerHTML += row;
                });
            } else {
                noHistoryMsg.classList.remove('hidden');
            }

            document.getElementById('details-modal').classList.remove('hidden');
        }

        function closeDetailsModal(e) {
            if (e.target.id === 'details-modal') document.getElementById('details-modal').classList.add('hidden');
        }

        // ... Keep Remove Modal Logic Same as Before ...
        function openRemoveModal(id, name, maxStock) {
            document.getElementById('remove-id').value = id;
            document.getElementById('remove-item-name').innerText = name;
            document.getElementById('remove-item-stock').innerText = maxStock;
            const qtyInput = document.getElementById('remove-qty');
            qtyInput.max = maxStock;
            qtyInput.value = 1;
            document.getElementById('remove-modal').classList.remove('hidden');
        }

        function closeRemoveModal(e) {
            if (e.target.id === 'remove-modal') document.getElementById('remove-modal').classList.add('hidden');
        }

        // Check if there are Laravel errors on page load
        @if($errors->any())
            // If there is an error, scroll to the top so the user sees the red alert
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Optional: Alert the user
            // alert('يوجد خطأ في البيانات المدخلة، يرجى التحقق من الرسالة في أعلى الصفحة.');
        @endif
    </script>
</body>

</html>