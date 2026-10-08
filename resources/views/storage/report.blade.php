<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير حركة المخزن</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .print-border {
                border: 1px solid #000;
            }
        }
    </style>
    @include('partials.app-styles')
</head>

<body class="app-shell">

    @include('partials.account-nav')

    <div class="app-content">

        <div class="page-heading">
            <div>
                <div class="eyebrow">المخزون / التقارير</div><h1>تقرير حركة المخزن</h1>
                <p class="text-gray-500 mt-1">
                    @if($dateInputs['start'] == '2000-01-01')
                        عرض <span class="font-bold text-blue-600">كل السجلات</span> (من البداية)
                    @else
                        الفترة من: <span class="font-bold">{{ $dateInputs['start'] }}</span>
                        إلى: <span class="font-bold">{{ $dateInputs['end'] }}</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('storage.index') }}"
                class="no-print ui-button">
                <x-icon name="arrow" /> عودة للمخزن
            </a>
        </div>

        <div class="no-print ui-panel p-6 mb-8">
            <form action="{{ route('storage.report') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-1">بحث شامل</label>
                        <input type="text" name="search" value="{{ $querySearch }}"
                            placeholder="اسم الصنف، المستلم، الوجهة، SN..."
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">من تاريخ</label>
                        <input type="date" name="start_date" value="{{ $dateInputs['start'] }}"
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">إلى تاريخ</label>
                        <input type="date" name="end_date" value="{{ $dateInputs['end'] }}"
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">نوع الحركة</label>
                        <select name="type"
                            class="w-full border p-2 rounded outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                            <option value="all" {{ $typeFilter == 'all' ? 'selected' : '' }}>الكل</option>
                            <option value="in" {{ $typeFilter == 'in' ? 'selected' : '' }}>📥 وارد (إضافة)</option>
                            <option value="out" {{ $typeFilter == 'out' ? 'selected' : '' }}>📤 صادر (سحب)</option>
                        </select>
                    </div>

                </div>

                <div class="flex flex-wrap gap-2 mt-4">
                    <button type="submit"
                        class="ui-button ui-button-primary">
                        <x-icon name="search" /> تصفية النتائج
                    </button>

                    <button type="submit" name="show_all" value="1"
                        class="ui-button">
                        <x-icon name="history" /> عرض كل السجلات
                    </button>

                    <button type="button" onclick="window.print()"
                        class="ui-button mr-auto">
                        🖨️ طباعة
                    </button>
                </div>
            </form>
        </div>

        <p class="text-sm text-gray-500 mb-3">تاريخ العملية هو التاريخ المُدخل. وقت التسجيل هو وقت حفظها في النظام ({{ config('app.timezone') }}). تظهر العمليات الملغاة للاطلاع ولا تُحتسب في الرصيد الحالي.</p>
        <div class="bg-white shadow-lg rounded-lg overflow-x-auto border print-border">
            <table class="w-full text-right">
                <thead class="bg-gray-200 text-gray-700 border-b print-border">
                    <tr>
                        <th class="p-4">نوع الحركة</th>
                        <th class="p-4">تاريخ العملية</th>
                        <th class="p-4">الصنف</th>
                        <th class="p-4">الرقم التسلسلي (SN)</th>
                        <th class="p-4">الكمية</th>
                        <th class="p-4">الطرف الآخر (مستلم/وجهة)</th>
                        <th class="p-4">ملاحظات</th>
                        <th class="p-4">سجّلها / وقت التسجيل</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $trans)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="p-4">
                                @if($trans['type'] == 'in')
                                    <span
                                        class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-bold print-border whitespace-nowrap">📥
                                        {{ $trans['action_label'] }}</span>
                                @else
                                    <span
                                        class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-bold print-border whitespace-nowrap">📤
                                        {{ $trans['action_label'] }}</span>
                                @endif
                            </td>

                            <td class="p-4 text-gray-600 dir-ltr whitespace-nowrap">
                                {{ $trans['date']->format('Y-m-d') }}
                                <span class="text-xs text-gray-400 block">{{ $trans['date']->format('H:i') }}</span>
                            </td>

                            <td class="p-4 font-bold text-gray-800">{{ $trans['name'] }}</td>

                            <td class="p-4 text-gray-600 font-mono text-sm">{{ $trans['sn'] ?? '-' }}</td>

                            <td class="p-4">
                                <span class="font-bold {{ $trans['type'] == 'in' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $trans['type'] == 'in' ? '+' : '-' }}{{ $trans['quantity'] }}
                                </span>
                            </td>

                            <td class="p-4 text-gray-600">{{ $trans['party'] }}</td>

                            <td class="p-4 text-sm text-gray-500">
                                {{ $trans['note'] }}
                                @if($trans['cancelled'])
                                    <span class="block font-bold text-red-700 mt-1">ملغاة — لا تؤثر على الرصيد الحالي</span>
                                    <span class="block">{{ $trans['cancellation_details'] }}</span>
                                @endif
                            </td>
                            <td class="p-4 text-sm">
                                <span class="block font-bold text-gray-700">{{ $trans['recorded_by'] }}</span>
                                <span class="block text-xs text-gray-500 whitespace-nowrap" dir="ltr">{{ $trans['recorded_at'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center text-gray-500">لا توجد حركات مخزنية تطابق البحث.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4 dir-ltr no-print" dir="ltr">
                {{ $transactions->links() }}
            </div>
        </div>

        <div class="mt-4 text-left text-gray-500 text-sm">
            عدد النتائج: {{ count($transactions) }} | تم الاستخراج: {{ now()->format('Y-m-d H:i') }}
        </div>

    </div>
</body>

</html>
