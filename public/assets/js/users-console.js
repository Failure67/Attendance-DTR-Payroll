$(document).ready(function() {
    
    const usersConsole = new ModalConsole('addUsersModal');

    usersConsole.bindField('full_name', 'Full name');
    
    usersConsole.bindField('email', 'Email');

    usersConsole.bindSelect('role', 'Role');

    usersConsole.bindField('birthdate', 'Birthdate');

    usersConsole.bindSelect('gender', 'Gender');

    usersConsole.bindField('sss_number', 'SSS number');

    usersConsole.bindField('philhealth_number', 'PhilHealth number');

    usersConsole.bindField('pagibig_number', 'Pag-IBIG number');

    usersConsole.bindSelect('employment_type', 'Employment type');

    usersConsole.bindField('employment_start_date', 'Employment start date');

    usersConsole.$modal.on('input', '[name="password"]', function() {
        const value = $(this).val();
        if (value) {
            usersConsole.updateConsole('Password', '●●●●●●●● (hidden)');
        } else {
            usersConsole.updateConsole('Password', 'N/A (hidden)');
        }
    });

    usersConsole.$modal.on('hidden.bs.modal', function() {
        usersConsole.reset({
            'Full name': 'N/A',
            'Email': 'N/A',
            'Role': 'N/A',
            'Birthdate': 'N/A',
            'Gender': 'N/A',
            'SSS number': 'N/A',
            'PhilHealth number': 'N/A',
            'Pag-IBIG number': 'N/A',
            'Employment type': 'N/A',
            'Employment start date': 'N/A',
            'Password': 'N/A (hidden)',
        });

        $('#addUsersForm')[0].reset();
    });
    
});
