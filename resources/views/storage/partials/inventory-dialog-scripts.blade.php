    <script>
        // 1. Base URL
        const APP_URL = "{{ url('/') }}";

        // 2. Data from Controller
        let currentItemData = {};

        function escHtml(s) {
            return (s ?? '').toString().replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c]));
        }

        // One visible dialog; closing a correction returns to the same item details.
        const modalStack = [];
        const focusableSelector = 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), summary, [tabindex="0"]';

        function openModal(id) {
            const modal = document.getElementById(id);
            const previous = modalStack[modalStack.length - 1];
            if (previous?.id === id) return;
            const trigger = document.activeElement;
            if (previous) document.getElementById(previous.id).classList.add('hidden');
            modalStack.push({ id, trigger });
            modal.classList.remove('hidden');
            document.body.classList.add('modal-open');
            modal.focus();
            document.querySelectorAll('[data-page-content]').forEach(el => el.inert = true);
        }

        function closeModal(id) {
            if (modalStack[modalStack.length - 1]?.id !== id) return;
            const closed = modalStack.pop();
            document.getElementById(id).classList.add('hidden');
            const previous = modalStack[modalStack.length - 1];
            if (previous) {
                document.getElementById(previous.id).classList.remove('hidden');
            } else {
                document.body.classList.remove('modal-open');
                document.querySelectorAll('[data-page-content]').forEach(el => el.inert = false);
            }
            if (closed.trigger?.isConnected) closed.trigger.focus();
        }

        document.addEventListener('keydown', event => {
            const current = modalStack[modalStack.length - 1];
            if (!current) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(current.id);
            } else if (event.key === 'Tab') {
                const modal = document.getElementById(current.id);
                const controls = [...modal.querySelectorAll(focusableSelector)].filter(el => el.getClientRects().length);
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && (document.activeElement === first || document.activeElement === modal)) {
                    event.preventDefault(); last?.focus();
                } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === modal)) {
                    event.preventDefault(); first?.focus();
                }
            }
        });

        function formatDates(iso) {
            const d = new Date(iso);
            const displayDate = d.toLocaleDateString('ar-EG', { numberingSystem: 'latn' }) + ' ' + d.toLocaleTimeString('ar-EG', { numberingSystem: 'latn', hour: '2-digit', minute: '2-digit' });
            const pad = (n) => String(n).padStart(2, '0');
            const inputDate = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            return { displayDate, inputDate };
        }

        function openCancelModal(kind, id) {
            const unusedItem = kind === 'unused-item';
            const itemAction = kind === 'item' || unusedItem;
            document.getElementById('cancel-form').action = unusedItem ? `${APP_URL}/storage/item/${id}/cancel` : `${APP_URL}/storage/${kind}/${id}`;
            document.getElementById('cancel-title').textContent = unusedItem ? 'إلغاء الصنف' : kind === 'item' ? 'أرشفة العنصر' : 'إلغاء العملية';
            document.getElementById('cancel-explanation').textContent = kind === 'item'
                ? 'يمكن أرشفة الصنف إذا كان رصيده صفراً. ستبقى جميع سجلاته متاحة.'
                : unusedItem ? 'يُلغى الصنف وتُزال كميته من المخزون مع حفظ بياناته الأصلية. هذا متاح فقط قبل أول إضافة أو سحب.'
                : kind === 'out' ? 'ستعاد الكمية للمخزون مع الاحتفاظ بالعملية الأصلية وسجل الإلغاء.'
                : 'ستخصم الكمية من الرصيد إذا كانت متاحة، مع الاحتفاظ بالعملية الأصلية وسجل الإلغاء.';
            document.getElementById('cancel-reason').value = '';
            document.getElementById('cancel-item-name').textContent = currentItemData.name;
            const movement = itemAction ? null : (kind === 'out' ? currentItemData.outs : currentItemData.additions).find(m => m.id === id);
            document.getElementById('cancel-operation').textContent = movement ? `${kind === 'out' ? 'سحب' : 'إضافة'} · ${movement.quantity} وحدة` : unusedItem ? `${currentItemData.stock} وحدة` : 'أرشفة الصنف';
            document.getElementById('cancel-submit').textContent = kind === 'item' ? 'تأكيد الأرشفة' : 'تأكيد الإلغاء';
            openModal('cancel-modal');
            document.getElementById('cancel-reason').focus();
        }

        function renderMovements(bodyId, emptyId, movements, kind) {
            document.getElementById(emptyId).classList.toggle('hidden', movements.length > 0);
            document.getElementById(bodyId).innerHTML = movements.map(m => {
                const cancelled = !!m.cancelled_at;
                const cancellation = cancelled ? `<span class="status-badge status-red mt-2">ملغاة — لا تؤثر على الرصيد الحالي</span><span class="cancellation-copy">${escHtml(m.cancelled_by_name)} · ${escHtml(formatDates(m.cancelled_at).displayDate)} · ${escHtml(m.cancellation_reason)}</span>` : '';
                let controls = '';
                if (m.can_correct && !cancelled && !currentItemData.archived && !currentItemData.cancelled) {
                    controls = `<div class="movement-actions"><button type="button" onclick="${kind === 'out' ? 'openEditOutModal' : 'openEditAdditionModal'}(${m.id})" class="ui-button ui-button-warning-soft ui-button-small"><x-icon name="edit" />تعديل العملية</button><button type="button" onclick="openCancelModal('${kind}', ${m.id})" class="ui-button ui-button-danger-soft ui-button-small"><x-icon name="cancel" />إلغاء العملية</button></div>`;
                }
                return `<tr class="border-b ${cancelled ? 'cancelled-row' : ''}">
                    <td class="p-2 text-gray-600">${escHtml(formatDates(m.date).displayDate)}</td>
                    <td class="p-2 font-bold ${cancelled ? 'line-through text-gray-500' : kind === 'out' ? 'text-red-600' : 'text-green-600'}">${kind === 'out' ? '-' : '+'}${m.quantity}</td>
                    <td class="p-2">${escHtml((kind === 'out' ? m.destination : m.source) || '-')}</td>
                    <td class="p-2 text-xs text-gray-600">${escHtml(m.note || '-')}${cancellation}</td>
                    <td class="p-2 text-xs"><span class="block font-bold">${escHtml(m.recorded_by_label)}</span><span class="block whitespace-nowrap text-gray-500" dir="ltr">${escHtml(m.recorded_at_display)}</span></td>
                    <td class="p-2">${controls}</td>
                </tr>`;
            }).join('');
        }

        // --- OPEN DETAILS MODAL ---
        function openDetailsModal(btn) {
            showItemDetails({
                id: btn.getAttribute('data-id'),
                name: btn.getAttribute('data-name'),
                category: btn.getAttribute('data-category'),
                stock: btn.getAttribute('data-stock'),
                initialQuantity: btn.getAttribute('data-initial-quantity'),
                manufacturer: btn.getAttribute('data-manufacturer'),
                model: btn.getAttribute('data-model'),
                sn: btn.getAttribute('data-sn'),
                receiver: btn.getAttribute('data-receiver'),
                date: btn.getAttribute('data-date'),
                desc: btn.getAttribute('data-desc'),
                recordedBy: btn.getAttribute('data-recorded-by'),
                recordedAt: btn.getAttribute('data-recorded-at'),
                archived: btn.getAttribute('data-archived') === '1',
                cancelled: btn.getAttribute('data-cancelled') === '1',
                canCorrect: btn.getAttribute('data-can-correct') === '1',
                canReplace: btn.getAttribute('data-can-replace') === '1',
                cancellationReason: btn.getAttribute('data-cancellation-reason'),
                outs: JSON.parse(btn.getAttribute('data-outs') || '[]'),
                additions: JSON.parse(btn.getAttribute('data-additions') || '[]'),
            });
        }

        function showItemDetails(data) {
            currentItemData = data;

            // Fill Static Data
            document.getElementById('modal-title').innerText = currentItemData.name;
            document.getElementById('modal-category').innerText = currentItemData.category;
            document.getElementById('modal-stock').textContent = currentItemData.stock;
            document.getElementById('modal-stock').closest('.info-field').classList.toggle('info-stock-empty', Number(currentItemData.stock) <= 0);
            document.getElementById('modal-manufacturer').innerText = currentItemData.manufacturer || 'غير محدد';
            document.getElementById('modal-model').innerText = currentItemData.model || 'غير محدد';
            document.getElementById('modal-receiver').innerText = currentItemData.receiver || 'غير محدد';
            document.getElementById('modal-date').innerText = currentItemData.date.slice(0, 10);
            document.getElementById('modal-desc').innerText = currentItemData.desc || 'غير محدد';
            document.getElementById('modal-sn').innerText = currentItemData.sn || 'غير محدد';
            document.getElementById('modal-recorded-by').innerText = currentItemData.recordedBy;
            document.getElementById('modal-recorded-at').innerText = currentItemData.recordedAt;
            document.getElementById('edit-item-button').classList.toggle('hidden', !currentItemData.canCorrect || currentItemData.archived || currentItemData.cancelled);
            document.getElementById('cancel-item-button').classList.toggle('hidden', !currentItemData.canReplace);
            const cancellationNote = document.getElementById('item-cancellation-note');
            cancellationNote.classList.toggle('hidden', !currentItemData.cancelled);
            cancellationNote.textContent = currentItemData.cancelled ? `صنف ملغى — ${currentItemData.cancellationReason || ''}` : '';

            // Set Delete Action
            @can('admin')
            document.getElementById('delete-item-form').action = `${APP_URL}/storage/item/${currentItemData.id}`;
            document.getElementById('delete-item-form').classList.toggle('hidden', currentItemData.archived);
            document.getElementById('item-history-link').href = `${APP_URL}/audits?product_in_id=${currentItemData.id}`;
            @endcan

            renderMovements('modal-additions-body', 'modal-no-additions', currentItemData.additions, 'addition');
            renderMovements('modal-history-body', 'modal-no-history', currentItemData.outs, 'out');
            openModal('details-modal');
        }

        function openEditItemModal() {
            document.getElementById('edit-item-form').action = `${APP_URL}/storage/item/${currentItemData.id}`;
            document.getElementById('edit-name').value = currentItemData.name;
            document.getElementById('edit-category').value = currentItemData.category;
            document.getElementById('edit-manufacturer').value = currentItemData.manufacturer;
            document.getElementById('edit-model').value = currentItemData.model;
            document.getElementById('edit-sn').value = currentItemData.sn;
            document.getElementById('edit-receiver').value = currentItemData.receiver;
            document.getElementById('edit-date').value = currentItemData.date; // Ensure this is also formatted similarly if needed
            document.getElementById('edit-desc').value = currentItemData.desc;
            document.getElementById('edit-item-quantity').value = currentItemData.initialQuantity;
            document.getElementById('edit-item-quantity').disabled = !currentItemData.canReplace;
            document.getElementById('edit-item-quantity-hint').textContent = currentItemData.canReplace
                ? 'يمكن تصحيح الكمية قبل تسجيل أي إضافة أو سحب.'
                : 'لا يمكن تعديل الكمية بعد تسجيل إضافة أو سحب، حتى لو أُلغيت الحركة.';
            document.querySelector('#edit-item-form [name="reason"]').value = '';
            openModal('edit-item-modal');
        }

        function openEditMovementModal(kind, id) {
            const record = (kind === 'out' ? currentItemData.outs : currentItemData.additions).find(m => m.id === id);
            const field = kind === 'out' ? 'destination' : 'source';
            document.getElementById(`edit-${kind}-form`).action = `${APP_URL}/storage/${kind}/${id}`;
            document.getElementById(`edit-${kind}-product`).value = currentItemData.id;
            document.getElementById(`edit-${kind}-${field}`).value = record[field] || '';
            document.getElementById(`edit-${kind}-date`).value = formatDates(record.date).inputDate;
            document.getElementById(`edit-${kind}-note`).value = record.note || '';
            document.getElementById(`edit-${kind}-reason`).value = '';
            document.getElementById(`edit-${kind}-item-name`).textContent = currentItemData.name;
            document.getElementById(`edit-${kind}-original-quantity`).textContent = `${kind === 'out' ? 'سحب' : 'إضافة'} · ${record.quantity} وحدة`;
            openModal(`edit-${kind}-modal`);
        }

        function openEditOutModal(id) { openEditMovementModal('out', id); }
        function openEditAdditionModal(id) { openEditMovementModal('addition', id); }

        function openAddModal(id, name) {
            document.getElementById('add-id').value = id;
            document.getElementById('add-item-name').innerText = name;
            openModal('add-modal');
        }

        function openRemoveModal(id, name, max) {
            document.getElementById('remove-id').value = id;
            document.getElementById('remove-item-name').innerText = name;
            document.getElementById('remove-item-stock').innerText = max;
            document.getElementById('remove-qty').max = max;
            document.getElementById('remove-qty').value = 1;
            openModal('remove-modal');
        }

        function closeModalIfOutside(e, id) { if (e.target.id === id) closeModal(id); }

    </script>
