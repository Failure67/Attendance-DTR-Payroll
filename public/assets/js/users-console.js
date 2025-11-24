$(document).ready(function() {
    
    const usersConsole = new ModalConsole('addUsersModal');

    usersConsole.bindField('full_name', 'Full name');
    
    usersConsole.bindField('email', 'Email');

    usersConsole.bindSelect('role', 'Role');

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
            'Password': 'N/A (hidden)',
        });

        $('#addUsersForm')[0].reset();
    });
    
});
