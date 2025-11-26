$(document).ready(function() {

    const payrollStep = new ModalStep('addPayrollModal', {
        totalSteps: 2,

        onBeforeNext: function(currentStep) {
            if (currentStep === 1) {
                const employeeId = this.$modal.find('[name="user_id"]').val();
                const wageType = this.$modal.find('[name="wage_type"]').val();
                const grossPay = this.$modal.find('[name="gross_pay"]').val().replace(/,/g, '');

                if (!employeeId) {
                    alert('Please select the name of employee.');
                    return false;
                }
                if (!wageType || wageType === '') {
                    alert('Please select the type of wage');
                    return false;
                }
                if (!grossPay || parseFloat(grossPay) <= 0) {
                    alert('Please enter the gross pay.');
                    return false;
                }
            }
            return true;
        },

        onStepChange: function(step) {
            console.log('Current step:', step);

            if (step === 1) {
                this.$modal.find('.modal-title').text('New Payroll');
            } else if (step === 2) {
                this.$modal.find('.modal-title').text('Review Payroll');
            }
        },

        onSubmit: function() {
            console.log('Submitting form...');

            const netPay = this.$modal.find('.console-item').filter(function() {
                return $(this).find('.console-label').text().trim() === 'Net pay:';
            }).find('console-value').text();

            console.log('Net pay to be submitted:', netPay);
            return true;
        }
    });

});