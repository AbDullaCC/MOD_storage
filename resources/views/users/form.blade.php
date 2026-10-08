@extends('layouts.account')
@section('title', $user->exists ? 'تعديل الحساب' : 'إنشاء حساب')
@section('content')
<section class="max-w-xl mx-auto bg-white rounded-lg shadow p-6">
    <h1 class="text-2xl font-bold mb-6">{{ $user->exists ? 'تعديل الحساب' : 'إنشاء حساب' }}</h1>
    <form action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" method="POST" class="space-y-4">
        @csrf
        @if($user->exists) @method('PUT') @endif
        <div><label for="name" class="block mb-1">الاسم</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="w-full border rounded p-3"></div>
        <div><label for="email" class="block mb-1">البريد الإلكتروني</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" dir="ltr" class="w-full border rounded p-3"></div>
        <div><label for="role" class="block mb-1">الصلاحية</label><select id="role" name="role" class="w-full border rounded p-3"><option value="operator" @selected(old('role', $user->role ?? 'operator') === 'operator')>موظف إدخال — عرض وإضافة وسحب</option><option value="admin" @selected(old('role', $user->role) === 'admin')>مدير — إدارة المستخدمين وجميع العمليات</option></select></div>
        <div><label for="is_active" class="block mb-1">الحالة</label><select id="is_active" name="is_active" class="w-full border rounded p-3"><option value="1" @selected((string) old('is_active', $user->exists ? (int) $user->is_active : 1) === '1')>نشط</option><option value="0" @selected((string) old('is_active', $user->exists ? (int) $user->is_active : 1) === '0')>معطّل</option></select></div>
        <div><label for="password" class="block mb-1">{{ $user->exists ? 'كلمة مرور جديدة (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور' }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" @required(!$user->exists) dir="ltr" class="w-full border rounded p-3"><p class="text-sm text-gray-500 mt-1">8 أحرف على الأقل.</p></div>
        <div><label for="password_confirmation" class="block mb-1">تأكيد كلمة المرور</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$user->exists) dir="ltr" class="w-full border rounded p-3"></div>
        <div class="flex gap-4 items-center"><button type="submit" class="bg-blue-600 text-white rounded px-5 py-3 font-bold">حفظ الحساب</button><a href="{{ route('users.index') }}" class="text-gray-600">إلغاء</a></div>
    </form>
</section>
@endsection
