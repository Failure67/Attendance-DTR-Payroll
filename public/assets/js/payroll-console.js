$(document).ready(function() {
    
    const payrollConsole = new ModalConsole('addPayrollModal', {
        currencySymbol: '₱'
    });

    payrollConsole.bindSelect('user_id', 'Employee name');

    payrollConsole.bindSelect('wage_type', 'Wage type');

    const wageUnitLabelMap = {
        'Hourly': 'hour/s',
        'Daily': 'day/s',
        'Weekly': 'week/s',
        'Monthly': 'month/s',
        'Piece rate': 'unit/s',
    };

    function getCurrentUnitLabel() {
        const wageType = payrollConsole.$modal.find('[name="wage_type"]').val();
        return wageUnitLabelMap[wageType] || 'unit/s';
    }

    function updateUnitsLabelAndConsole() {
        const label = getCurrentUnitLabel();
        const $unitsInput = payrollConsole.$modal.find('#units-worked-payroll');

        if ($unitsInput.length) {
            const $container = $unitsInput.closest('.input-field-container');
            const $labelSpan = $container.find('.input-number-label');
            if ($labelSpan.length) {
                $labelSpan.text(label);
            }
        }

        const units = payrollConsole.$modal.find('[name="units_worked"]').val() || '0';
        payrollConsole.updateConsole('Units worked', `${units} ${label}`);
    }

    function recalculateGrossPay() {
        const minRaw = payrollConsole.$modal.find('[name="min_wage"]').val().replace(/,/g, '');
        const unitsRaw = payrollConsole.$modal.find('[name="units_worked"]').val();

        const min = parseFloat(minRaw) || 0;
        const units = parseFloat(unitsRaw) || 0;

        const gross = min * units;
        const $grossInput = payrollConsole.$modal.find('[name="gross_pay"]');

        if (gross > 0) {
            $grossInput.val(gross.toFixed(2));
        } else {
            $grossInput.val('');
        }

        $grossInput.trigger('input');
    }

    payrollConsole.$modal.on('input', '[name="min_wage"]', function() {
        const rawValue = $(this).val().replace(/,/g, '');
        payrollConsole.updateConsole('Minimum wage', payrollConsole.formatAmount(rawValue));
        recalculateGrossPay();
    });

    payrollConsole.$modal.on('input', '[name="units_worked"]', function() {
        recalculateGrossPay();
        updateUnitsLabelAndConsole();
    });

    payrollConsole.$modal.on('change', '[name="wage_type"]', function() {
        updateUnitsLabelAndConsole();
        recalculateGrossPay();
    });

    payrollConsole.$modal.on('input', '[name="gross_pay"]', function() {
        const rawValue = $(this).val().replace(/,/g, '');
        payrollConsole.updateConsole('Gross pay', payrollConsole.formatAmount(rawValue));
        calculateNetPay();
    });

    payrollConsole.bindSelect('status', 'Status');

    function calculateNetPay() {
        const grossPayRaw = payrollConsole.$modal.find('[name="gross_pay"]').val().replace(/,/g, '');
        const grossPay = parseFloat(grossPayRaw) || 0;
        
        let totalDeductions = 0;
        payrollConsole.$modal.find('input[name^="deductions"][name$="[amount]"]').each(function() {
            const amount = parseFloat($(this).val()) || 0;
            totalDeductions += amount;
        });

        const netPay = grossPay - totalDeductions;

        payrollConsole.updateConsole('Deductions', payrollConsole.formatAmount(totalDeductions));
        payrollConsole.updateConsole('Net pay', payrollConsole.formatAmount(netPay));
    }

    payrollConsole.observeChanges('.manage-item-container', function() {
        calculateNetPay();
    });

    payrollConsole.$modal.on('input change', 'input[name^="deductions"]', function() {
        calculateNetPay();
    });

    payrollConsole.$modal.on('hidden.bs.modal', function() {
        payrollConsole.reset({
            'Employee name': 'N/A',
            'Wage type': 'Daily',
            'Minimum wage': '₱0.00',
            'Units worked': '0 day/s',
            'Gross pay': '₱0.00',
            'Deductions': '₱0.00',
            'Net pay': '₱0.00',
            'Status': 'Pending',
        });

        $('#addPayrollForm')[0].reset();

        updateUnitsLabelAndConsole();
        recalculateGrossPay();
    });

    // initial sync
    updateUnitsLabelAndConsole();
    
});