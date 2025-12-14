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
                <input type="text" name="category" placeholder="التصنيف (مثلاً: لابتوب - سيارة)"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>
                <input type="text" name="manufacturer" placeholder="الشركة المصنعة"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                <input type="number" name="quantity" placeholder="الكمية"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>
                <input type="text" name="serial_number"
                    placeholder="الرقم التسلسلي (يمكن تركه فارغاً بحالة كانت الكمية اكبر من 1)"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none">
                <input type="text" name="reciever" placeholder="اسم المستلم"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none" required>
                <input type="datetime-local" name="added_at"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-400 outline-none text-right" required>

                <button type="submit" class="bg-blue-600 text-white p-2 rounded font-bold hover:bg-blue-700 transition">
                    + إضافة للمخزون
                </button>
            </form>
        </div>

        <form method="GET" class="mb-6">
            <div class="relative text-gray-600 focus-within:text-gray-400">
                <input type="text" name="search" placeholder="بحث بالاسم، الرقم التسلسلي، أو التصنيف..."
                    class="w-full border p-3 rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-blue-400"
                    value="{{ request('search') }}">
            </div>
        </form>

        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
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
                            <td class="p-4">
                                @if($product->current_stock > 0)
                                    <form action="{{ route('storage.out') }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="product_in_id" value="{{ $product->id }}">
                                        <input type="hidden" name="date" value="{{ now() }}">

                                        <input type="number" name="quantity" placeholder="العدد"
                                            class="w-20 border p-1 rounded text-sm text-center"
                                            max="{{ $product->current_stock }}" min="1" required>
                                        <input type="text" name="destination" placeholder="الوجهة"
                                            class="w-32 border p-1 rounded text-sm" required>

                                        <button type="submit"
                                            class="bg-red-500 text-white px-4 py-1 rounded text-sm hover:bg-red-600 transition shadow">
                                            سحب
                                        </button>
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
</body>

</html>