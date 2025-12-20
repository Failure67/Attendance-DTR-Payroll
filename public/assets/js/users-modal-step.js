$(document).ready(function() {

    const usersStep = new ModalStep('addUsersModal', {
        totalSteps: 2,

        onBeforeNext: function(currentStep) {
            if (currentStep === 1) {
                const mode = this.$modal.attr('data-mode') || 'create';
                const fullName = this.$modal.find('[name="full_name"]').val();
                const email = this.$modal.find('[name="email"]').val();
                const role = this.$modal.find('[name="role"]').val();
                const password = this.$modal.find('[name="password"]').val();
                const birthdate = this.$modal.find('[name="birthdate"]').val();
                const gender = this.$modal.find('[name="gender"]').val();

                if (!fullName || fullName.trim() === '') {
                    alert('Please enter the full name.');
                    return false;
                }
                if (!email || email.trim() === '') {
                    alert('Please enter the email address.');
                    return false;
                }
                if (!role || role === '') {
                    alert('Please select a role');
                    return false;
                }
                if (!birthdate || birthdate.trim() === '') {
                    alert('Please enter the birthdate.');
                    return false;
                }
                if (!gender || gender === '') {
                    alert('Please select a gender');
                    return false;
                }
                if (mode !== 'edit') {
                    if (!password || password.trim() === '') {
                        alert('Please enter a password.');
                        return false;
                    }
                    if (password.length < 12) {
                        alert('Password must be at least 12 characters.');
                        return false;
                    }
                } else if (password && password.length > 0 && password.length < 12) {
                    alert('Password must be at least 12 characters.');
                    return false;
                }
            }
            return true;
        },

        onStepChange: function(step) {
            console.log('Current step:', step);

            const mode = this.$modal.attr('data-mode') || 'create';

            if (step === 1) {
                this.$modal.find('.modal-title').text(mode === 'edit' ? 'Edit User' : 'New User');
            } else if (step === 2) {
                this.$modal.find('.modal-title').text('Review User');
            }
        },

        onSubmit: function() {
            console.log('Submitting user form...');
            return true;
        }
    });

});
