<nav aria-label="الحساب" class="no-print bg-white border rounded-lg p-4 mb-6 flex flex-wrap items-center justify-between gap-3 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('storage.index') }}" class="font-bold text-blue-700">المخزون</a>
        <span>{{ auth()->user()->name }}</span>
        <span class="text-sm bg-gray-100 px-2 py-1 rounded">{{ auth()->user()->isAdmin() ? 'مدير' : 'موظف إدخال' }}</span>
    </div>
    <div class="flex flex-wrap items-center gap-4 text-sm">
        @can('admin')<a href="{{ route('users.index') }}" class="text-indigo-700 font-bold">إدارة المستخدمين</a>@endcan
        <a href="{{ route('password.edit') }}" class="text-gray-700">تغيير كلمة المرور</a>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="text-red-700 font-bold" type="submit">تسجيل الخروج</button>
        </form>
    </div>
</nav>
