$(document).ready(function() {
    
    const payrollConsole = new ModalConsole('addPayrollModal', {
        currencySymbol: '₱'
    });

    payrollConsole.bindField('employee_name', 'Employee name');

    payrollConsole.bindSelect('wage_type', 'Wage type');

    payrollConsole.$modal.on('input', '[name="min_wage"]', function() {
        const rawValue = $(this).val().replace(/,/g, '');
        payrollConsole.updateConsole('Minimum wage', payrollConsole.formatAmount(rawValue));
    });

    payrollConsole.$modal.on('input', '[name="units_worked"]', function() {
        const units = $(this).val() || '0';
        const unitType = payrollConsole.$modal.find('[name="wage_unit"] option:selected').text() || 'day/s';
        payrollConsole.updateConsole('Units worked', `${units} ${unitType}`);
    });

    payrollConsole.$modal.on('change', '[name="wage_unit"]', function() {
        const units = payrollConsole.$modal.find('[name="units_worked"]').val() || '0';
        const unitType = $(this).find('option:selected').text();
        payrollConsole.updateConsole('Units worked', `${units} ${unitType}`);
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
    });
    
});