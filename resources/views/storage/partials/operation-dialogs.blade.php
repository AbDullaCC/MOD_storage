<div id="edit-item-modal" role="dialog" aria-modal="true" aria-labelledby="edit-item-title" tabindex="-1" class="app-dialog hidden" onclick="closeModalIfOutside(event, 'edit-item-modal')">
    <div class="dialog-panel dialog-panel-medium dialog-tone-amber">
        <div class="dialog-header">
            <div class="dialog-heading"><span class="section-icon status-amber"><x-icon name="edit" /></span><div><h3 id="edit-item-title">تعديل بيانات الصنف</h3><p>صحّح البيانات مع توضيح سبب التعديل.</p></div></div>
            <button type="button" class="icon-button" onclick="closeModal('edit-item-modal')" aria-label="إغلاق تعديل العنصر"><x-icon name="close" /></button>
        </div>
        <form id="edit-item-form" method="POST" class="dialog-form">
            @csrf @method('PUT')
            <div class="dialog-form-fields">
                <label>اسم الصنف<input type="text" name="name" id="edit-name" required></label>
                <label>التصنيف<input type="text" name="category" id="edit-category" required></label>
                <label>الشركة المصنعة<input type="text" name="manufacturer" id="edit-manufacturer"></label>
                <label>الموديل<input type="text" name="model_type" id="edit-model"></label>
                <label>الرقم التسلسلي<input type="text" name="serial_number" id="edit-sn"></label>
                <label>المستلم<input type="text" name="reciever" id="edit-receiver"></label>
                <label for="edit-item-quantity">الكمية عند إنشاء الصنف<input type="number" name="quantity" id="edit-item-quantity" min="1" max="2147483647" step="1" required aria-describedby="edit-item-quantity-hint"><small id="edit-item-quantity-hint" class="form-hint"></small></label>
                <label>تاريخ إضافة الصنف<input type="datetime-local" lang="en" dir="ltr" name="added_at" id="edit-date" required max="{{ now()->format('Y-m-d\TH:i') }}"></label>
                <label class="full-width">الوصف<textarea name="description" id="edit-desc" rows="2"></textarea></label>
            </div>
            <label class="reason-field">سبب التعديل <span class="text-blue-600">*</span><textarea name="reason" required minlength="3" maxlength="1000" rows="2" placeholder="ما الذي استدعى تصحيح هذه البيانات؟"></textarea><small>يظهر هذا السبب في سجل العمليات مع اسمك ووقت التعديل.</small></label>
            <div class="dialog-footer"><button type="button" onclick="closeModal('edit-item-modal')" class="ui-button">رجوع للتفاصيل</button><button type="submit" class="ui-button ui-button-warning"><x-icon name="check" /> حفظ التعديلات</button></div>
        </form>
    </div>
</div>

@foreach(['out' => ['title' => 'تعديل عملية السحب', 'field' => 'destination', 'label' => 'الوجهة'], 'addition' => ['title' => 'تعديل عملية الإضافة', 'field' => 'source', 'label' => 'المصدر']] as $kind => $dialog)
<div id="edit-{{ $kind }}-modal" role="dialog" aria-modal="true" aria-labelledby="edit-{{ $kind }}-title" tabindex="-1" class="app-dialog hidden" onclick="closeModalIfOutside(event, 'edit-{{ $kind }}-modal')">
    <div class="dialog-panel dialog-panel-medium dialog-tone-amber">
        <div class="dialog-header">
            <div class="dialog-heading"><span class="section-icon status-amber"><x-icon name="edit" /></span><div><h3 id="edit-{{ $kind }}-title">{{ $dialog['title'] }}</h3><p>لتغيير الكمية، ألغِ العملية ثم سجّلها من جديد.</p></div></div>
            <button type="button" class="icon-button" onclick="closeModal('edit-{{ $kind }}-modal')" aria-label="إغلاق {{ $dialog['title'] }}"><x-icon name="close" /></button>
        </div>
        <div class="dialog-context"><x-icon name="box" /><strong id="edit-{{ $kind }}-item-name"></strong><span id="edit-{{ $kind }}-original-quantity" class="movement-quantity"></span></div>
        <form id="edit-{{ $kind }}-form" method="POST" class="dialog-form">
            @csrf @method('PUT')
            <div class="dialog-form-fields">
            <label class="full-width" for="edit-{{ $kind }}-product">الصنف<select id="edit-{{ $kind }}-product" name="product_in_id" required>
                @foreach($movementProducts as $productOption)
                    <option value="{{ $productOption->id }}">{{ $productOption->name }}</option>
                @endforeach
            </select></label>
            <label for="edit-{{ $kind }}-{{ $dialog['field'] }}">{{ $dialog['label'] }}<input type="text" name="{{ $dialog['field'] }}" id="edit-{{ $kind }}-{{ $dialog['field'] }}" placeholder="{{ $dialog['label'] }} (اختياري)"></label>
            <label for="edit-{{ $kind }}-date">تاريخ العملية<input type="datetime-local" lang="en" dir="ltr" name="date" id="edit-{{ $kind }}-date" required max="{{ now()->format('Y-m-d\TH:i') }}"></label>
            <label class="full-width" for="edit-{{ $kind }}-note">ملاحظات<textarea name="note" id="edit-{{ $kind }}-note" rows="2" placeholder="تفاصيل إضافية عن العملية"></textarea></label>
            </div>
            <label class="reason-field" for="edit-{{ $kind }}-reason">سبب التعديل <span class="text-blue-600">*</span><textarea id="edit-{{ $kind }}-reason" name="reason" required minlength="3" maxlength="1000" rows="2" placeholder="وضّح سبب تصحيح بيانات العملية"></textarea><small>سيُحفظ السبب مع اسمك في سجل العمليات.</small></label>
            <div class="dialog-footer"><button type="button" onclick="closeModal('edit-{{ $kind }}-modal')" class="ui-button">رجوع للتفاصيل</button><button type="submit" class="ui-button ui-button-warning"><x-icon name="check" /> حفظ التعديلات</button></div>
        </form>
    </div>
</div>
@endforeach

<div id="cancel-modal" role="dialog" aria-modal="true" aria-labelledby="cancel-title" aria-describedby="cancel-explanation" tabindex="-1" class="app-dialog hidden" onclick="closeModalIfOutside(event, 'cancel-modal')">
    <div class="dialog-panel dialog-tone-red">
        <div class="dialog-header">
            <div class="dialog-heading"><span class="section-icon status-red"><x-icon name="cancel" /></span><div><h3 id="cancel-title">إلغاء العملية</h3><p>تأكد من تفاصيل العملية قبل المتابعة.</p></div></div>
            <button type="button" class="icon-button" onclick="closeModal('cancel-modal')" aria-label="إغلاق الإلغاء"><x-icon name="close" /></button>
        </div>
        <div class="dialog-context"><x-icon name="box" /><strong id="cancel-item-name"></strong><span id="cancel-operation" class="movement-quantity"></span></div>
        <form id="cancel-form" method="POST" class="dialog-form">
            @csrf @method('DELETE')
            <p id="cancel-explanation" class="cancel-notice"></p>
            <label for="cancel-reason">السبب <span class="text-rose-600">*</span><textarea id="cancel-reason" name="reason" required minlength="3" maxlength="1000" rows="3" placeholder="وضّح السبب لتسهيل مراجعة العملية لاحقاً"></textarea></label>
            <span class="form-hint"><x-icon name="shield" /> تبقى العملية الأصلية وتفاصيل الإلغاء محفوظة.</span>
            <div class="dialog-footer"><button type="button" onclick="closeModal('cancel-modal')" class="ui-button">رجوع للتفاصيل</button><button id="cancel-submit" type="submit" class="ui-button ui-button-danger">تأكيد الإلغاء</button></div>
        </form>
    </div>
</div>

@foreach(['add' => ['title' => 'إضافة كمية', 'subtitle' => 'تسجيل دفعة جديدة لهذا الصنف.', 'route' => 'storage.addition', 'field' => 'source', 'label' => 'المصدر', 'action' => 'تأكيد الإضافة'], 'remove' => ['title' => 'سحب من المخزن', 'subtitle' => 'تسجيل الكمية المسحوبة ووجهتها.', 'route' => 'storage.out', 'field' => 'destination', 'label' => 'الوجهة', 'action' => 'تأكيد السحب']] as $kind => $dialog)
<div id="{{ $kind }}-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $kind }}-title" tabindex="-1" class="app-dialog hidden" onclick="closeModalIfOutside(event, '{{ $kind }}-modal')">
    <div class="dialog-panel {{ $kind === 'add' ? 'dialog-tone-green' : 'dialog-tone-red' }}">
        <div class="dialog-header"><div class="dialog-heading"><span class="section-icon {{ $kind === 'add' ? 'status-green' : 'status-red' }}"><x-icon :name="$kind === 'add' ? 'plus' : 'box'" /></span><div><h3 id="{{ $kind }}-title">{{ $dialog['title'] }}</h3><p>{{ $dialog['subtitle'] }}</p></div></div><button type="button" class="icon-button" onclick="closeModal('{{ $kind }}-modal')" aria-label="إغلاق {{ $dialog['title'] }}"><x-icon name="close" /></button></div>
        <div class="dialog-context"><x-icon name="box" /><strong id="{{ $kind }}-item-name"></strong>@if($kind === 'remove')<span class="movement-quantity">المتوفر: <span id="remove-item-stock">0</span></span>@endif</div>
        <form action="{{ route($dialog['route']) }}" method="POST" class="dialog-form">
            @csrf
            <input type="hidden" name="product_in_id" id="{{ $kind }}-id">
            <label>الكمية<input type="number" name="quantity" id="{{ $kind }}-qty" min="1" value="1" required></label>
            <label>{{ $dialog['label'] }}<input type="text" name="{{ $dialog['field'] }}" placeholder="{{ $dialog['label'] }} (اختياري)"></label>
            <label>تاريخ العملية<input type="datetime-local" lang="en" dir="ltr" name="date" value="{{ now()->format('Y-m-d\TH:i') }}" max="{{ now()->format('Y-m-d\TH:i') }}" required></label>
            <label>ملاحظات<textarea name="note" rows="2" placeholder="تفاصيل إضافية عن العملية"></textarea></label>
            <div class="dialog-footer"><button type="button" onclick="closeModal('{{ $kind }}-modal')" class="ui-button">رجوع</button><button type="submit" class="ui-button {{ $kind === 'add' ? 'ui-button-success' : 'ui-button-danger' }}"><x-icon name="check" />{{ $dialog['action'] }}</button></div>
        </form>
    </div>
</div>
@endforeach
