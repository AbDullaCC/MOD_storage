@extends('layouts.account')
@section('title', 'تغيير كلمة المرور')
@section('content')
<section class="max-w-lg mx-auto bg-white rounded-lg shadow p-6">
    <div class="panel-heading"><span class="section-icon"><x-icon name="key" /></span><div><h1 class="text-xl font-bold">تغيير كلمة المرور</h1><p>حافظ على أمان حسابك بكلمة مرور قوية.</p></div></div>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf @method('PUT')
        <div><label for="current_password" class="block mb-1">كلمة المرور الحالية</label><input class="w-full border rounded p-3" dir="ltr" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
        <div><label for="password" class="block mb-1">كلمة المرور الجديدة (8 أحرف على الأقل)</label><input class="w-full border rounded p-3" dir="ltr" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
        <div><label for="password_confirmation" class="block mb-1">تأكيد كلمة المرور</label><input class="w-full border rounded p-3" dir="ltr" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
        <button type="submit" class="ui-button ui-button-primary"><x-icon name="check" />حفظ كلمة المرور</button>
    </form>
</section>
@endsection
