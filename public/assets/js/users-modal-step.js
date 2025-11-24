$(document).ready(function() {

    const usersStep = new ModalStep('addUsersModal', {
        totalSteps: 2,

        onBeforeNext: function(currentStep) {
            if (currentStep === 1) {
                const fullName = this.$modal.find('[name="full_name"]').val();
                const email = this.$modal.find('[name="email"]').val();
                const role = this.$modal.find('[name="role"]').val();
                const password = this.$modal.find('[name="password"]').val();

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
                if (!password || password.trim() === '') {
                    alert('Please enter a password.');
                    return false;
                }
                if (password.length < 8) {
                    alert('Password must be at least 8 characters.');
                    return false;
                }
            }
            return true;
        },

        onStepChange: function(step) {
            console.log('Current step:', step);

            if (step === 1) {
                this.$modal.find('.modal-title').text('New User');
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
