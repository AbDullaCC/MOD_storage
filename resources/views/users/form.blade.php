@extends('layouts.account')
@section('title', $user->exists ? 'تعديل الحساب' : 'إنشاء حساب')
@section('content')
<section class="max-w-xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="panel-heading"><span class="section-icon"><x-icon name="users" /></span><div><h1 class="text-xl font-bold">{{ $user->exists ? 'تعديل الحساب' : 'إنشاء حساب' }}</h1><p>بيانات الحساب والصلاحيات</p></div></div>
    <form action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" method="POST" class="space-y-4">
        @csrf
        @if($user->exists) @method('PUT') @endif
        <div><label for="name" class="block mb-1">الاسم</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="w-full border rounded p-3"></div>
        <div><label for="username" class="block mb-1">اسم المستخدم</label><input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="50" autocomplete="off" autocapitalize="none" spellcheck="false" dir="auto" class="w-full border rounded p-3"><p class="text-sm text-gray-500 mt-1">3–50 حرفاً. أحرف وأرقام ونقطة وشرطة (-) أو شرطة سفلية (_)، دون مسافات.</p></div>
        <div><label for="role" class="block mb-1">الصلاحية</label><select id="role" name="role" class="w-full border rounded p-3"><option value="operator" @selected(old('role', $user->role ?? 'operator') === 'operator')>موظف إدخال — عرض وإضافة وسحب</option><option value="admin" @selected(old('role', $user->role) === 'admin')>مدير — إدارة المستخدمين وجميع العمليات</option></select></div>
        <div><label for="is_active" class="block mb-1">الحالة</label><select id="is_active" name="is_active" class="w-full border rounded p-3"><option value="1" @selected((string) old('is_active', $user->exists ? (int) $user->is_active : 1) === '1')>نشط</option><option value="0" @selected((string) old('is_active', $user->exists ? (int) $user->is_active : 1) === '0')>معطّل</option></select></div>
        <div><label for="password" class="block mb-1">{{ $user->exists ? 'كلمة مرور جديدة (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور' }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" @required(!$user->exists) dir="ltr" class="w-full border rounded p-3"><p class="text-sm text-gray-500 mt-1">8 أحرف على الأقل.</p></div>
        <div><label for="password_confirmation" class="block mb-1">تأكيد كلمة المرور</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$user->exists) dir="ltr" class="w-full border rounded p-3"></div>
        <div class="form-footer"><a href="{{ route('users.index') }}" class="ui-button">إلغاء</a><button type="submit" class="ui-button ui-button-primary"><x-icon name="check" />حفظ الحساب</button></div>
    </form>
</section>
@endsection
