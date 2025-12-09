$(document).ready(function() {
    const $tableContainer = $('.table-container.payroll-table');
    const $table = $tableContainer.find('table').first();
    const $search = $('#payroll-search');

    const $wrapper = $('.wrapper.payroll');
    const isArchivedView = $wrapper.data('archived') === 1 || $wrapper.data('archived') === '1';

    const $payrollModal = $('#addPayrollModal');
    const $payrollForm = $('#addPayrollForm');

    const $deleteBtn = $('#payroll-delete-payroll');
    const $deleteLabel = $deleteBtn.find('.button-label');
    const $deleteIcon = $deleteBtn.find('.button-icon i');

    let selectedPayrollId = null;
    const selectedPayrollIds = new Set();
    let lastSelectedIndex = null;

    function updateDeleteButtonState() {
        if (!$deleteBtn.length || !$deleteLabel.length) return;

        const count = selectedPayrollIds.size;

        if (count > 0) {
            if (isArchivedView) {
                $deleteLabel.text('Delete permanently');
            } else {
                $deleteLabel.text('Delete');
            }
            if ($deleteIcon.length) {
                $deleteIcon.removeClass('fa-clock-rotate-left').addClass('fa-trash');
            }
        } else {
            if (isArchivedView) {
                $deleteLabel.text('Back to payroll');
            } else {
                $deleteLabel.text('View archived');
            }
            if ($deleteIcon.length) {
                $deleteIcon.removeClass('fa-trash').addClass('fa-clock-rotate-left');
            }
        }
    }

    // Row selection: behave like attendance (toggle per click), with Shift+click range support
    if ($table.length) {
        $table.on('click', 'tbody tr', function(e) {
            // Ignore clicks on inline action buttons
            if ($(e.target).closest('.payroll-actions, .payroll-archive-actions').length) {
                return;
            }

            const $rows = $table.find('tbody tr');
            const $row = $(this);
            const index = $rows.index($row);
            const $employeeCell = $row.find('.payroll-employee').first();
            const id = $employeeCell.data('payroll-id');
            if (!id) return;

            const idStr = String(id);

            if (e.shiftKey && lastSelectedIndex !== null) {
                // Shift+click: select a continuous range between lastSelectedIndex and this row
                const start = Math.min(lastSelectedIndex, index);
                const end = Math.max(lastSelectedIndex, index);

                for (let i = start; i <= end; i++) {
                    const $r = $rows.eq(i);
                    const $cell = $r.find('.payroll-employee').first();
                    const rid = $cell.data('payroll-id');
                    if (!rid) continue;
                    const ridStr = String(rid);
                    if (!$r.hasClass('selected')) {
                        $r.addClass('selected');
                        selectedPayrollIds.add(ridStr);
                    }
                }
            } else {
                // Simple toggle on this row (same feel as attendance)
                if ($row.hasClass('selected')) {
                    $row.removeClass('selected');
                    selectedPayrollIds.delete(idStr);
                } else {
                    $row.addClass('selected');
                    selectedPayrollIds.add(idStr);
                }

                lastSelectedIndex = index;
            }

            selectedPayrollId = selectedPayrollIds.size ? Array.from(selectedPayrollIds)[selectedPayrollIds.size - 1] : null;

            updateDeleteButtonState();
        });
    }

    // Search filter (simple client-side filter on current page)
    if ($search.length && $table.length) {
        $search.on('input', function() {
            const term = $(this).val().trim().toLowerCase();

            $table.find('tbody tr').each(function() {
                const text = $(this).text().toLowerCase();
                const matches = !term || text.indexOf(term) !== -1;
                $(this).toggle(matches);
            });
        });
    }

    // New payroll: ensure form is in create mode
    $('#payroll-add-payroll').on('click', function() {
        if (!$payrollForm.length) return;

        selectedPayrollId = null;

        $payrollForm.attr('action', '/payroll/create');
        $payrollForm.find('input[name="_method"]').remove();

        if ($payrollForm[0]) {
            $payrollForm[0].reset();
        }

        const $employeeSelect = $payrollForm.find('select[name="user_id"]');
        if ($employeeSelect.length) {
            $employeeSelect.val(null).trigger('change');
        }

        $payrollModal.find('.modal-title').text('New Payroll');
    });

    // Edit selected payroll
    $('#payroll-edit-payroll').on('click', function() {
        if (!selectedPayrollId) {
            alert('Please select a payroll record to edit.');
            return;
        }

        if (!$payrollForm.length) return;

        $.getJSON(`/payroll/${selectedPayrollId}`, function(data) {
            // Switch form to update mode
            $payrollForm.attr('action', `/payroll/${selectedPayrollId}`);
            $payrollForm.find('input[name="_method"]').remove();
            $('<input>', { type: 'hidden', name: '_method', value: 'PUT' }).appendTo($payrollForm);

            if ($payrollForm[0]) {
                $payrollForm[0].reset();
            }

            $payrollModal.find('.modal-title').text('Edit Payroll');

            // Employee
            const $employeeSelect = $payrollForm.find('select[name="user_id"]');
            if ($employeeSelect.length && data.user_id) {
                $employeeSelect.val(String(data.user_id)).trigger('change');
            }

            // Wage type
            if (data.wage_type) {
                $payrollForm.find('select[name="wage_type"]').val(data.wage_type).trigger('change');
            }

            // Minimum wage
            if (typeof data.min_wage !== 'undefined') {
                const min = parseFloat(data.min_wage) || 0;
                $payrollForm.find('input[name="min_wage"]').val(min.toFixed(2)).trigger('input');
            }

            // Units worked (from hours or days depending on wage type)
            let units = 0;
            if (data.wage_type === 'Hourly' || data.wage_type === 'Piece rate') {
                units = parseFloat(data.hours_worked) || 0;
            } else {
                units = parseFloat(data.days_worked) || 0;
            }
            $payrollForm.find('input[name="units_worked"]').val(units).trigger('input');

            // Gross pay
            if (typeof data.gross_pay !== 'undefined') {
                const gross = parseFloat(data.gross_pay) || 0;
                $payrollForm.find('input[name="gross_pay"]').val(gross.toFixed(2)).trigger('input');
            }

            // Status (map Released -> Completed for UI)
            let statusUi = data.status || 'Pending';
            if (statusUi === 'Released') statusUi = 'Completed';
            $payrollForm.find('select[name="status"]').val(statusUi).trigger('change');

            // Deductions
            const $manageItem = $payrollForm.find('.manage-item-option[data-name="deductions"]').first();
            const $container = $manageItem.find('.manage-item-container');
            $container.empty();

            if (Array.isArray(data.deductions) && data.deductions.length) {
                data.deductions.forEach(function(ded, idx) {
                    const name = ded.name || '';
                    const amountNum = parseFloat(ded.amount) || 0;
                    const amountFixed = amountNum.toFixed(2);
                    const displayAmount = '₱ ' + amountFixed.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

                    if (idx > 0) {
                        $container.append('<hr>');
                    }

                    const $item = $('<div>', { 'class': 'item-option', 'data-index': idx });
                    const $label = $('<div>', { 'class': 'item-label' });
                    $label.append($('<span>', { 'class': 'item-name', text: name }));
                    $label.append(' | ');
                    $label.append($('<span>', { 'class': 'item-amount', text: displayAmount }));
                    $item.append($label);

                    $item.append($('<input>', {
                        type: 'hidden',
                        name: `deductions[${idx}][name]`,
                        value: name,
                    }));

                    $item.append($('<input>', {
                        type: 'hidden',
                        name: `deductions[${idx}][amount]`,
                        value: amountFixed,
                    }));

                    const $action = $('<div>', { 'class': 'item-action' });
                    $action.append($('<div>', { 'class': 'item-edit' }).append('<i class="fa-solid fa-pencil"></i>'));
                    $action.append($('<div>', { 'class': 'item-remove' }).append('<i class="fa-solid fa-xmark"></i>'));
                    $item.append($action);

                    $container.append($item);
                });
            }

            const modalInstance = new bootstrap.Modal($payrollModal[0]);
            modalInstance.show();
        }).fail(function() {
            alert('Unable to load payroll details for editing.');
        });
    });

    // Delete payrolls or toggle archived view
    $('#payroll-delete-payroll').on('click', function() {
        const selectedCount = selectedPayrollIds.size;

        // No selection: act as View archived / Back to payroll toggle
        if (selectedCount === 0) {
            const url = new URL(window.location.href);
            const params = url.searchParams;

            if (isArchivedView) {
                params.delete('archived');
            } else {
                params.set('archived', '1');
            }

            url.search = params.toString();
            window.location.href = url.toString();
            return;
        }

        const ids = Array.from(selectedPayrollIds);

        const $modal = $('#deletePayrollModal');
        $modal.data('payrollIds', ids);

        if (ids.length === 1) {
            const $row = $table.find('tbody tr').filter(function () {
                const $cell = $(this).find('.payroll-employee').first();
                return String($cell.data('payroll-id')) === String(ids[0]);
            }).first();
            const employeeName = $row.find('.payroll-employee').text().trim();
            $modal.find('#confirm-item-name').text(`payroll for ${employeeName}?`);
        } else {
            $modal.find('#confirm-item-name').text(`these ${ids.length} payroll records?`);
        }

        const modalInstance = new bootstrap.Modal($modal[0]);
        modalInstance.show();
    });

    $('#confirm-delete-payroll').on('click', function(e) {
        e.preventDefault();

        const $modal = $('#deletePayrollModal');
        const ids = $modal.data('payrollIds') || [];
        if (!ids.length) {
            $modal.modal('hide');
            return;
        }

        const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = isArchivedView ? '/payroll?archived=1' : '/payroll';
        form.style.display = 'none';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        form.appendChild(methodInput);

        ids.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'payroll_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        if (isArchivedView) {
            const archivedInput = document.createElement('input');
            archivedInput.type = 'hidden';
            archivedInput.name = 'archived';
            archivedInput.value = '1';
            form.appendChild(archivedInput);
        }

        document.body.appendChild(form);
        form.submit();
    });

    function openPayrollDetails(payrollId) {
        if (!payrollId) return;

        $.getJSON(`/payroll/${payrollId}`, function(data) {
            const $modal = $('#payrollDetailsModal');
            if (!$modal.length) {
                return;
            }

            const formatMoney = function(value) {
                const num = parseFloat(value) || 0;
                return '₱ ' + num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            };

            const period = (data.period_start && data.period_end)
                ? `${data.period_start} to ${data.period_end}`
                : (data.created_at ? (data.created_at.split(' ')[0] || data.created_at) : 'N/A');

            $modal.find('#payroll-details-employee').text(data.employee_name || 'N/A');
            $modal.find('#payroll-details-period').text(period);
            $modal.find('#payroll-details-created-at').text(data.created_at || 'N/A');

            $modal.find('#payroll-details-wage-type').text(data.wage_type || 'N/A');
            $modal.find('#payroll-details-min-wage').text(formatMoney(data.min_wage));

            const units = (data.wage_type === 'Hourly' || data.wage_type === 'Piece rate')
                ? (parseFloat(data.hours_worked) || 0)
                : (parseFloat(data.days_worked) || 0);
            const unitLabelMap = {
                'Hourly': 'hour/s',
                'Daily': 'day/s',
                'Weekly': 'week/s',
                'Monthly': 'month/s',
                'Piece rate': 'unit/s',
            };
            const unitLabel = unitLabelMap[data.wage_type] || 'unit/s';
            $modal.find('#payroll-details-units-worked').text(units + ' ' + unitLabel);

            $modal.find('#payroll-details-regular-hours').text((parseFloat(data.regular_hours) || 0).toFixed(2));
            $modal.find('#payroll-details-overtime-hours').text((parseFloat(data.overtime_hours) || 0).toFixed(2));
            $modal.find('#payroll-details-absent-days').text((parseFloat(data.absent_days) || 0).toFixed(2));

            $modal.find('#payroll-details-gross-pay').text(formatMoney(data.gross_pay));
            $modal.find('#payroll-details-total-deductions').text(formatMoney(data.total_deductions));
            $modal.find('#payroll-details-net-pay').text(formatMoney(data.net_pay));

            const $dedList = $modal.find('#payroll-details-deductions-list');
            $dedList.empty();
            if (Array.isArray(data.deductions) && data.deductions.length) {
                data.deductions.forEach(function(d) {
                    const name = d.name || 'Deduction';
                    const amount = formatMoney(d.amount);
                    $dedList.append(`<li><span>${name}</span><span class="float-end fw-semibold">${amount}</span></li>`);
                });
            } else {
                $dedList.append('<li class="text-muted">No manual deductions.</li>');
            }

            const $caList = $modal.find('#payroll-details-ca-list');
            $caList.empty();
            if (Array.isArray(data.cash_advances) && data.cash_advances.length) {
                data.cash_advances.forEach(function(ca) {
                    const amount = formatMoney(ca.amount);
                    const desc = ca.description || 'Cash advance repayment';
                    $caList.append(`<li><span>${desc}</span><span class="float-end fw-semibold">${amount}</span></li>`);
                });
            } else {
                $caList.append('<li class="text-muted">No cash advance deductions in this payroll.</li>');
            }

            const modalInstance = new bootstrap.Modal($modal[0]);
            modalInstance.show();
        }).fail(function() {
            alert('Unable to load payroll details.');
        });
    }

    // Toolbar Details (from More actions): open details for the single selected payroll
    $('#payroll-more-details').on('click', function() {
        if (!selectedPayrollIds.size) {
            alert('Please select a payroll record to view details.');
            return;
        }

        if (selectedPayrollIds.size > 1) {
            alert('Please select only one payroll record to view details.');
            return;
        }

        const id = Array.from(selectedPayrollIds)[0];
        openPayrollDetails(id);
    });

    // More actions: Process from attendance
    const $processBtn = $('#payroll-more-process');
    if ($processBtn.length) {
        $processBtn.on('click', function () {
            const url = $(this).data('url');
            if (url) {
                window.location.href = url;
            }
        });
    }

    // More actions: Export CSV
    const $exportCsvBtn = $('#payroll-more-export-csv');
    if ($exportCsvBtn.length) {
        $exportCsvBtn.on('click', function () {
            const url = $(this).data('url');
            if (url) {
                window.location.href = url;
            }
        });
    }

    // More actions: Export PDF
    const $exportPdfBtn = $('#payroll-more-export-pdf');
    if ($exportPdfBtn.length) {
        $exportPdfBtn.on('click', function () {
            const url = $(this).data('url');
            if (url) {
                window.location.href = url;
            }
        });
    }

    // Row-level actions: Complete / Cancel
    if ($table.length) {
        $table.on('click', '.payroll-action.complete, .payroll-action.cancel', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const payrollId = $btn.data('id');
            if (!payrollId) {
                return;
            }

            const isComplete = $btn.hasClass('complete');

            const $row = $btn.closest('tr');
            const employeeName = $row.find('.payroll-employee').text().trim() || 'this employee';

            const confirmText = isComplete
                ? `Mark payroll for ${employeeName} as completed?`
                : `Cancel payroll for ${employeeName}?`;

            if (!confirm(confirmText)) {
                return;
            }

            // Optimistic UI: hide actions in this row immediately
            const $actionsContainer = $row.find('.payroll-actions');
            if ($actionsContainer.length) {
                $actionsContainer.remove();
            }

            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/payroll/${payrollId}/status`;
            form.style.display = 'none';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'PATCH';
            form.appendChild(methodInput);

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = isComplete ? 'Completed' : 'Cancelled';
            form.appendChild(statusInput);

            document.body.appendChild(form);
            form.submit();
        });
    }

    // Initialize delete/view-archived button state on load
    updateDeleteButtonState();
});
