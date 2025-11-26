$(document).ready(function() {
    const $tableContainer = $('.table-container.payroll-table');
    const $table = $tableContainer.find('table').first();
    const $search = $('#payroll-search');

    const $payrollModal = $('#addPayrollModal');
    const $payrollForm = $('#addPayrollForm');

    let selectedPayrollId = null;

    // Row selection (for delete)
    if ($table.length) {
        $table.on('click', 'tbody tr', function() {
            $table.find('tbody tr').removeClass('selected');
            $(this).addClass('selected');

            const $employeeCell = $(this).find('.payroll-employee').first();
            selectedPayrollId = $employeeCell.data('payroll-id') || null;
        });
    }

    // Search filter
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

    // Delete single payroll using confirm modal
    $('#payroll-delete-payroll').on('click', function() {
        if (!selectedPayrollId) {
            alert('Please select a record to delete.');
            return;
        }

        const $row = $table.find('tbody tr.selected');
        const employeeName = $row.find('.payroll-employee').text().trim();

        const $modal = $('#deletePayrollModal');
        $modal.find('#confirm-item-name').text(`payroll for ${employeeName}?`);
        $modal.data('payrollId', selectedPayrollId);

        const modalInstance = new bootstrap.Modal($modal[0]);
        modalInstance.show();
    });

    $('#confirm-delete-payroll').on('click', function(e) {
        e.preventDefault();

        const $modal = $('#deletePayrollModal');
        const payrollId = $modal.data('payrollId');
        if (!payrollId) {
            $modal.modal('hide');
            return;
        }

        const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/payroll/${payrollId}`;
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

        document.body.appendChild(form);
        form.submit();
    });

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
});
