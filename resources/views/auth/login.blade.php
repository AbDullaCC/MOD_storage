@extends('layouts.account')
@section('title', 'تسجيل الدخول')
@section('content')
<section class="max-w-md mx-auto mt-12 bg-white rounded-xl shadow-md border-t-4 border-blue-600 p-8">
    <h1 class="text-2xl font-bold mb-2">نظام إدارة المخزون</h1>
    <p class="text-gray-500 mb-6">سجّل الدخول باستخدام الحساب الذي أنشأه المدير.</p>
    <form action="{{ route('login.store') }}" method="POST" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="block font-bold mb-2">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" dir="ltr" class="w-full border rounded-lg p-3">
        </div>
        <div>
            <label for="password" class="block font-bold mb-2">كلمة المرور</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" dir="ltr" class="w-full border rounded-lg p-3">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg p-3">تسجيل الدخول</button>
    </form>
    <p class="text-sm text-gray-500 mt-5">لإنشاء حساب أو استعادة الوصول، تواصل مع المدير.</p>
</section>
@endsection
