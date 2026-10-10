    <div id="details-modal"
        role="dialog" aria-modal="true" aria-labelledby="modal-title" tabindex="-1" class="app-dialog hidden"
        onclick="closeModalIfOutside(event, 'details-modal')">
        <div
            class="dialog-panel dialog-panel-wide modal-scroll">

            <div class="dialog-header">
                <div>
                    <div class="dialog-heading"><span class="section-icon"><x-icon name="box" /></span><div><h3 id="modal-title">...</h3><p>تفاصيل الصنف وحركات المخزون</p></div></div>
                    <div class="details-actions">
                        @can('admin')
                        <a id="item-history-link" class="ui-button ui-button-soft ui-button-small"><x-icon name="history" />سجل العمليات</a>
                        @endcan
                        <button id="edit-item-button" onclick="openEditItemModal()"
                            class="ui-button ui-button-warning-soft ui-button-small"><x-icon name="edit" />
                            تعديل بيانات الصنف</button>
                        <button id="cancel-item-button" type="button" onclick="openCancelModal('unused-item', currentItemData.id)" class="ui-button ui-button-danger-soft ui-button-small"><x-icon name="cancel" />إلغاء الصنف</button>
                        @can('admin')
                        <form id="delete-item-form" method="POST"
                            onsubmit="openCancelModal('item', currentItemData.id); return false;"
                            class="inline">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="ui-button ui-button-danger-soft ui-button-small"><x-icon name="archive" />
                                أرشفة العنصر</button>
                        </form>
                        @endcan
                    </div>
                    <p id="item-cancellation-note" class="cancel-notice hidden mt-4"></p>
                </div>
                <button type="button" onclick="closeModal('details-modal')" aria-label="إغلاق التفاصيل" class="icon-button"><x-icon name="close" /></button>
            </div>

            @include('storage.partials.item-info')

            <div class="mb-6 movement-in">
                <h4 class="movement-heading"><span class="section-icon status-green"><x-icon name="plus" /></span>سجل الإضافات <span class="form-hint">دفعات جديدة</span></h4>
                <div class="movement-table">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr>
                                <th class="p-2">تاريخ العملية</th>
                                <th class="p-2">الكمية</th>
                                <th class="p-2">المصدر</th>
                                <th class="p-2">ملاحظات</th>
                                <th class="p-2">سجّلها / وقت التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="modal-additions-body"></tbody>
                    </table>
                    <p id="modal-no-additions" class="text-center p-4 text-gray-400 hidden">لا توجد دفعات إضافية لهذا
                        العنصر.</p>
                </div>
            </div>

            <div class="movement-out">
                <h4 class="movement-heading"><span class="section-icon status-red"><x-icon name="history" /></span>سجل المسحوبات</h4>
                <div class="movement-table">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr>
                                <th class="p-2">تاريخ العملية</th>
                                <th class="p-2">الكمية</th>
                                <th class="p-2">الوجهة</th>
                                <th class="p-2">ملاحظات</th>
                                <th class="p-2">سجّلها / وقت التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="modal-history-body"></tbody>
                    </table>
                    <p id="modal-no-history" class="text-center p-4 text-gray-400 hidden">لا توجد عمليات سحب لهذا
                        العنصر.</p>
                </div>
            </div>
        </div>
    </div>
