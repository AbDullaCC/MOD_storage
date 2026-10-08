@extends('layouts.account')
@section('title', 'إضافة صنف جديد')
@section('content')
<div class="item-entry-page">
    <div class="breadcrumb"><a href="{{ route('storage.index') }}">المخزون</a><x-icon name="chevron" /><span>إضافة صنف جديد</span></div>
    <div class="page-heading">
        <div><div class="eyebrow">المخزون / إدخال جديد</div><h1>إضافة صنف جديد</h1><p>سجّل بيانات الصنف والكمية المتوفرة عند إضافته للمخزن.</p></div>
        <a href="{{ route('storage.index') }}" class="ui-button"><x-icon name="arrow" /> العودة للمخزون</a>
    </div>
    <section class="ui-panel inventory-create">
        <form id="new-item-form" action="{{ route('storage.store') }}" method="POST">
            @csrf
            <div class="entry-section">
                <div class="panel-heading"><span class="section-icon status-green"><x-icon name="box" /></span><div><h2>بيانات الصنف</h2><p>الحقول المعلّمة بـ <span class="text-green-700">*</span> مطلوبة.</p></div></div>
                <div class="entry-fields">
                    <label for="item-name">اسم الصنف <span class="text-green-700">*</span><input id="item-name" type="text" name="name" value="{{ old('name') }}" placeholder="مثال: جهاز راوتر" required autofocus></label>
                    <label for="item-category">التصنيف <span class="text-green-700">*</span><input id="item-category" type="text" name="category" value="{{ old('category') }}" placeholder="مثال: تقانة" required></label>
                    <label for="item-manufacturer">الشركة المصنعة<input id="item-manufacturer" type="text" name="manufacturer" value="{{ old('manufacturer') }}" placeholder="اسم الشركة (اختياري)"></label>
                    <label for="item-model">الموديل / النوع<input id="item-model" type="text" name="model_type" value="{{ old('model_type') }}" placeholder="الموديل أو النوع (اختياري)"></label>
                    <label for="item-serial">الرقم التسلسلي<input id="item-serial" type="text" name="serial_number" value="{{ old('serial_number') }}" placeholder="SN (اختياري)"></label>
                    <label for="item-receiver">اسم المستلم<input id="item-receiver" type="text" name="reciever" value="{{ old('reciever') }}" placeholder="اختياري"></label>
                </div>
            </div>
            <div class="entry-section">
                <div class="panel-heading"><span class="section-icon status-green"><x-icon name="plus" /></span><div><h2>الكمية وتاريخ الإضافة</h2><p>لزيادة كمية صنف موجود، استخدم زر «إضافة» بجانبه في صفحة المخزون.</p></div></div>
                <div class="entry-fields">
                    <label for="item-quantity">الكمية <span class="text-green-700">*</span><input id="item-quantity" type="number" name="quantity" min="1" step="1" value="{{ old('quantity') }}" placeholder="عدد الوحدات" required></label>
                    <label for="item-date">تاريخ إضافة الصنف <span class="text-green-700">*</span><input id="item-date" type="datetime-local" name="added_at" value="{{ old('added_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" required></label>
                    <label for="item-description" class="entry-full-width">ملاحظات<textarea id="item-description" name="description" placeholder="أي تفاصيل إضافية عن الصنف..." rows="3">{{ old('description') }}</textarea></label>
                </div>
            </div>
            <div class="form-footer"><span class="form-hint"><x-icon name="shield" />تُسجّل العملية باسم حسابك الحالي.</span><div class="heading-actions"><a href="{{ route('storage.index') }}" class="ui-button">إلغاء</a><button type="submit" class="ui-button ui-button-success"><x-icon name="check" /> حفظ الصنف</button></div></div>
        </form>
    </section>
</div>
@endsection
