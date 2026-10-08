@extends('layouts.account')
@section('title', 'إدارة المستخدمين')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">إعدادات مساحة العمل</div><h1>إدارة المستخدمين</h1><p>إدارة حسابات الفريق والصلاحيات وحالة الوصول.</p></div>
    <a href="{{ route('users.create') }}" class="ui-button ui-button-primary"><x-icon name="plus" />إنشاء حساب</a>
</div>
<div class="ui-panel inventory-table">
    <table class="w-full text-right">
        <thead class="bg-gray-50"><tr><th class="p-4">الاسم</th><th class="p-4">اسم المستخدم</th><th class="p-4">الصلاحية</th><th class="p-4">الحالة</th><th class="p-4">الإجراءات</th></tr></thead>
        <tbody>
        @foreach($users as $user)
            <tr class="border-t"><td class="p-4"><div class="flex items-center gap-3"><span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><strong>{{ $user->name }}</strong></div></td><td class="p-4" dir="auto">{{ $user->username }}</td><td class="p-4"><span class="status-badge status-blue">{{ $user->role === 'admin' ? 'مدير' : 'موظف إدخال' }}</span></td><td class="p-4"><span class="status-badge {{ $user->is_active ? 'status-green' : 'status-slate' }}"><span class="status-dot"></span>{{ $user->is_active ? 'نشط' : 'معطّل' }}</span></td><td class="p-4"><a class="ui-button ui-button-small" href="{{ route('users.edit', $user) }}"><x-icon name="edit" />تعديل الحساب</a></td></tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $users->links() }}</div>
@endsection
