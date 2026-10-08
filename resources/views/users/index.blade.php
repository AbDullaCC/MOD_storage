@extends('layouts.account')
@section('title', 'إدارة المستخدمين')
@section('content')
<div class="flex flex-wrap justify-between items-center gap-3 mb-5">
    <h1 class="text-2xl font-bold">إدارة المستخدمين</h1>
    <a href="{{ route('users.create') }}" class="bg-blue-600 text-white rounded-lg px-4 py-3 font-bold">إنشاء حساب</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-right">
        <thead class="bg-gray-50"><tr><th class="p-4">الاسم</th><th class="p-4">البريد الإلكتروني</th><th class="p-4">الصلاحية</th><th class="p-4">الحالة</th><th class="p-4">الإجراءات</th></tr></thead>
        <tbody>
        @foreach($users as $user)
            <tr class="border-t"><td class="p-4">{{ $user->name }}</td><td class="p-4" dir="ltr">{{ $user->email }}</td><td class="p-4">{{ $user->role === 'admin' ? 'مدير' : 'موظف إدخال' }}</td><td class="p-4">{{ $user->is_active ? 'نشط' : 'معطّل' }}</td><td class="p-4"><a class="text-blue-700 font-bold" href="{{ route('users.edit', $user) }}">تعديل الحساب</a></td></tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $users->links() }}</div>
@endsection
