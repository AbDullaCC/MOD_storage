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

    <div class="max-w-7xl mx-auto">

        <h1 class="text-3xl font-bold mb-5 text-gray-800">📦 نظام إدارة المخزون</h1>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">نجاح!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">خطأ!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-xl font-bold mb-4 text-blue-900">📥 إدخال للمخزن (إضافة)</h2>

            <form action="{{ route('storage.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @csrf

                <input type="text" name="name" placeholder="اسم الصنف"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>

                <input type="text" name="category" placeholder="التصنيف (مثلاً: لابتوب)"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>

                <input type="text" name="manufacturer" placeholder="الشركة المصنعة"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">

                <input type="text" name="model_type" placeholder="الموديل / النوع (مثلاً: Pro 15)"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">

                <input type="number" name="quantity" placeholder="الكمية"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>

                <input type="text" name="serial_number" placeholder="الرقم التسلسلي (اختياري)"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">

                <input type="text" name="reciever" placeholder="اسم المستلم"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>

                <input type="datetime-local" name="added_at"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none text-right" required>

                <textarea name="description" placeholder="ملاحظات أو وصف إضافي..." rows="2"
                    class="col-span-1 md:col-span-4 border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none"></textarea>

                <button type="submit"
                    class="col-span-1 md:col-span-4 bg-blue-600 text-white p-2 rounded font-bold hover:bg-blue-700 transition shadow-lg">
                    + إضافة للمخزون
                </button>
            </form>
        </div>

        <form method="GET" class="mb-6" id="search-form">
            <div class="relative text-gray-600 focus-within:text-gray-400">
                <input type="text" name="search" id="search-input"
                    placeholder="بحث بالاسم، الرقم التسلسلي، أو التصنيف..."
                    class="w-full border p-3 rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-blue-400"
                    value="{{ request('search') }}">
            </div>
        </form>

        <div id="results-container"
            class="bg-white shadow-lg rounded-lg overflow-hidden transition-opacity duration-200">
            <table class="w-full text-right">
                <thead class="bg-gray-200 text-gray-700">
                    <tr>
                        <th class="p-4">الصنف / المصنع</th>
                        <th class="p-4">التصنيف</th>
                        <th class="p-4">الرقم التسلسلي (SN)</th>
                        <th class="p-4">حالة المخزون</th>
                        <th class="p-4">إخراج مواد (سحب)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($products as $product)
                        <tr class="hover:bg-blue-50 transition">
                            <td class="p-4">
                                <div class="font-bold text-gray-800">{{ $product->name }}</div>
                                <div class="text-xs text-gray-500">{{ $product->manufacturer }}</div>
                            </td>
                            <td class="p-4 text-gray-600">{{ $product->category }}</td>
                            <td class="p-4 text-sm font-mono text-gray-600">{{ $product->serial_number ?? '-' }}</td>
                            <td class="p-4">
                                <span
                                    class="px-3 py-1 rounded-full text-sm font-bold {{ $product->current_stock > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $product->current_stock }} / {{ $product->quantity }}
                                </span>
                            </td>
                            <td class="p-4 flex items-center gap-2">
                                <button onclick="openModal(this)"
                                    class="bg-blue-100 text-blue-600 px-3 py-1 rounded text-sm hover:bg-blue-200 transition font-bold"
                                    data-name="{{ $product->name }}" data-category="{{ $product->category }}"
                                    data-manufacturer="{{ $product->manufacturer ?? 'غير محدد' }}"
                                    data-model="{{ $product->model_type ?? 'غير محدد' }}"
                                    data-receiver="{{ $product->reciever }}" data-date="{{ $product->added_at }}"
                                    data-desc="{{ $product->description ?? 'لا يوجد وصف' }}"
                                    data-sn="{{ $product->serial_number ?? 'لا يوجد' }}">
                                    👁️ عرض
                                </button>

                                @if($product->current_stock > 0)
                                    <form action="{{ route('storage.out') }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="product_in_id" value="{{ $product->id }}">
                                        <input type="hidden" name="date" value="{{ now() }}">
                                        <input type="number" name="quantity" placeholder="العدد"
                                            class="w-16 border p-1 rounded text-sm text-center"
                                            max="{{ $product->current_stock }}" min="1" required>
                                        <input type="text" name="destination" placeholder="الوجهة"
                                            class="w-24 border p-1 rounded text-sm" required>
                                        <button type="submit"
                                            class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition shadow">سحب</button>
                                    </form>
                                @else
                                    <span class="text-gray-400 text-sm font-bold">نفذت الكمية</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(count($products) == 0)
                <div class="p-10 text-center text-gray-500">
                    لا توجد عناصر مطابقة للبحث.
                </div>
            @endif
        </div>
    </div>

    <div id="details-modal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex justify-center items-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg p-6 relative transform transition-all scale-100">

            <div class="border-b pb-3 mb-4 flex justify-between items-center">
                <h3 class="text-2xl font-bold text-gray-800" id="modal-title">تفاصيل العنصر</h3>
                <button onclick="closeModal()"
                    class="text-gray-500 hover:text-red-500 text-2xl font-bold">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-4 text-right">
                <div>
                    <p class="text-sm text-gray-500">التصنيف</p>
                    <p class="font-bold text-gray-800" id="modal-category">...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">الشركة المصنعة</p>
                    <p class="font-bold text-gray-800" id="modal-manufacturer">...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">الموديل / النوع</p>
                    <p class="font-bold text-gray-800" id="modal-model">...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">الرقم التسلسلي</p>
                    <p class="font-mono font-bold text-blue-600" id="modal-sn">...</p>
                </div>
                <div class="col-span-2 border-t pt-3 mt-2">
                    <p class="text-sm text-gray-500">تم الاستلام بواسطة</p>
                    <p class="font-bold text-gray-800" id="modal-receiver">...</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">تاريخ الإضافة</p>
                    <p class="font-bold text-gray-800" id="modal-date">...</p>
                </div>
                <div class="col-span-2 bg-gray-50 p-3 rounded">
                    <p class="text-sm text-gray-500">ملاحظات / وصف</p>
                    <p class="text-gray-700" id="modal-desc">...</p>
                </div>
            </div>

            <div class="mt-6 text-left">
                <button onclick="closeModal()"
                    class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300 font-bold">إغلاق</button>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('search-input');
        const resultsContainer = document.getElementById('results-container');
        let timeout = null;

        searchInput.addEventListener('input', function () {
            // Clear the previous timer (this resets the 1-second countdown)
            clearTimeout(timeout);

            // Visual feedback: dim the table slightly while waiting
            resultsContainer.style.opacity = '0.5';

            // Start a new 1-second timer
            timeout = setTimeout(() => {
                const query = searchInput.value;

                // Update the browser URL without reloading (so if they refresh, the search stays)
                const url = new URL(window.location);
                url.searchParams.set('search', query);
                window.history.pushState({}, '', url);

                // Fetch the new data from the server
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        // Parse the HTML response
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        // Extract the new table and put it in our current page
                        const newContainer = doc.getElementById('results-container').innerHTML;
                        resultsContainer.innerHTML = newContainer;

                        // Restore opacity
                        resultsContainer.style.opacity = '1';
                    })
                    .catch(err => console.error('Error fetching search results:', err));
            }, 1000); // 1000ms = 1 Second
        });

        // --- MODAL LOGIC ---
        function openModal(button) {
            // 1. Get data from the clicked button
            const name = button.getAttribute('data-name');
            const category = button.getAttribute('data-category');
            const manufacturer = button.getAttribute('data-manufacturer');
            const model = button.getAttribute('data-model');
            const receiver = button.getAttribute('data-receiver');
            const date = button.getAttribute('data-date');
            const desc = button.getAttribute('data-desc');
            const sn = button.getAttribute('data-sn');

            // 2. Fill the Modal HTML elements
            document.getElementById('modal-title').innerText = name;
            document.getElementById('modal-category').innerText = category;
            document.getElementById('modal-manufacturer').innerText = manufacturer;
            document.getElementById('modal-model').innerText = model;
            document.getElementById('modal-receiver').innerText = receiver;
            document.getElementById('modal-date').innerText = date;
            document.getElementById('modal-desc').innerText = desc;
            document.getElementById('modal-sn').innerText = sn;

            // 3. Show the modal
            document.getElementById('details-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('details-modal').classList.add('hidden');
        }

        // Close modal if user clicks outside the white box
        document.getElementById('details-modal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>

</html>