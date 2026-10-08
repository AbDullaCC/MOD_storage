<header class="app-header no-print" data-page-content>
    <div class="app-header-inner">
        <a href="{{ route('storage.index') }}" class="app-brand" aria-label="نظام إدارة المخزون — الرئيسية">
            <span class="brand-mark"><x-icon name="box" /></span>
            <span><strong>إدارة المخزون</strong><small>مساحة العمل</small></span>
        </a>
        <nav class="app-navigation" aria-label="التنقل الرئيسي">
            <a href="{{ route('storage.index') }}" class="nav-button {{ request()->routeIs('storage.index', 'storage.create', 'storage.history') ? 'is-active' : '' }}" @if(request()->routeIs('storage.index', 'storage.create', 'storage.history')) aria-current="page" @endif><x-icon name="box" /> المخزون</a>
            <a href="{{ route('storage.report') }}" class="nav-button {{ request()->routeIs('storage.report') ? 'is-active' : '' }}" @if(request()->routeIs('storage.report')) aria-current="page" @endif><x-icon name="chart" /> التقارير</a>
            @can('admin')
                <a href="{{ route('users.index') }}" class="nav-button {{ request()->routeIs('users.*') ? 'is-active' : '' }}" @if(request()->routeIs('users.*')) aria-current="page" @endif><x-icon name="users" /> إدارة المستخدمين</a>
            @endcan
        </nav>
        <div class="account-area" aria-label="الحساب">
            <span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="user-details">
                <div><strong dir="auto">{{ auth()->user()->name }}</strong><span class="role-badge">{{ auth()->user()->isAdmin() ? 'مدير' : 'موظف إدخال' }}</span></div>
                <small dir="ltr">{{ auth()->user()->email }}</small>
            </div>
            <div class="account-actions">
                <a href="{{ route('password.edit') }}" class="icon-button {{ request()->routeIs('password.edit') ? 'is-active' : '' }}" aria-label="تغيير كلمة المرور" title="تغيير كلمة المرور"><x-icon name="key" /></a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="icon-button icon-button-danger" type="submit" aria-label="تسجيل الخروج" title="تسجيل الخروج"><x-icon name="logout" /></button>
                </form>
            </div>
        </div>
    </div>
</header>
