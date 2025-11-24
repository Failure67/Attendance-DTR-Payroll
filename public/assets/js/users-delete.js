$(document).ready(function() {

    // Initialize delete functionality for users table
    const usersTable = $('.users-table');
    let selectedRows = [];

    // Add checkbox column header
    if (usersTable.length) {
        const thead = usersTable.find('thead tr');
        thead.prepend('<th class="checkbox-column"><input type="checkbox" id="select-all-users" class="select-all"></th>');

        const tbody = usersTable.find('tbody tr');
        tbody.each(function() {
            const userId = $(this).find('td:first').text().trim();
            $(this).prepend(`<td class="checkbox-column"><input type="checkbox" class="user-checkbox" value="${userId}"></td>`);
        });
    }

    // Select all checkbox functionality
    $(document).on('change', '#select-all-users', function() {
        const isChecked = $(this).is(':checked');
        $('.user-checkbox').prop('checked', isChecked);
        updateSelectedRows();
    });

    // Individual checkbox functionality
    $(document).on('change', '.user-checkbox', function() {
        updateSelectedRows();
        
        const allCheckboxes = $('.user-checkbox');
        const checkedCheckboxes = $('.user-checkbox:checked');
        
        if (allCheckboxes.length === checkedCheckboxes.length) {
            $('#select-all-users').prop('checked', true);
        } else {
            $('#select-all-users').prop('checked', false);
        }
    });

    // Update selected rows array
    function updateSelectedRows() {
        selectedRows = [];
        $('.user-checkbox:checked').each(function() {
            selectedRows.push($(this).val());
        });

        // Enable/disable delete button based on selection
        if (selectedRows.length > 0) {
            $('#users-delete-users').prop('disabled', false);
        } else {
            $('#users-delete-users').prop('disabled', true);
        }
    }

    // Delete button click handler
    $('#users-delete-users').on('click', function() {
        if (selectedRows.length === 0) {
            alert('Please select at least one user to delete.');
            return;
        }

        if (selectedRows.length === 1) {
            // Single delete - get the full name from the table row
            const userId = selectedRows[0];
            const $row = usersTable.find(`input[value="${userId}"]`).closest('tr');
            const fullName = $row.find('td:eq(1)').text().trim(); // Full name is in the 2nd column (after checkbox)
            showDeleteConfirm(userId, fullName, true);
        } else {
            // Multiple delete
            showDeleteConfirm(selectedRows, null, false);
        }
    });

    // Show delete confirmation modal
    function showDeleteConfirm(itemId, isSingle) {
        const $confirmModal = $('#deleteUsersModal');

        if (isSingle) {
            // Single user delete
            $confirmModal.find('.confirm-label span').text(`user (ID: ${itemId})?`);
            $confirmModal.data('deleteType', 'single');
            $confirmModal.data('userId', itemId);
        } else {
            // Multiple users delete
            $confirmModal.find('.confirm-label span').text(`${itemId.length} selected users?`);
            $confirmModal.data('deleteType', 'multiple');
            $confirmModal.data('userIds', itemId);
        }

        // Show modal
        const confirmModal = new bootstrap.Modal($confirmModal[0]);
        confirmModal.show();
    }

    // Submit delete form via AJAX
    $(document).on('click', '#confirm-delete-users', function(e) {
        e.preventDefault();
        
        const $confirmModal = $('#deleteUsersModal');
        const deleteType = $confirmModal.data('deleteType');
        const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

        let url, formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('_method', 'DELETE');

        if (deleteType === 'single') {
            const userId = $confirmModal.data('userId');
            url = `/users/${userId}`;
        } else {
            const userIds = $confirmModal.data('userIds');
            url = `/users`;
            userIds.forEach(id => {
                formData.append('user_ids[]', id);
            });
        }

        // Submit via traditional form or Fetch API
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        // Add method spoofing
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        form.appendChild(methodInput);

        // Add user IDs if multiple
        if (deleteType === 'multiple') {
            const userIds = $confirmModal.data('userIds');
            userIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = id;
                form.appendChild(input);
            });
        }

        // Append form to body and submit
        document.body.appendChild(form);
        form.submit();
    });

    // Reset on modal close
    $('#deleteUsersModal').on('hidden.bs.modal', function() {
        selectedRows = [];
        $('.user-checkbox').prop('checked', false);
        $('#select-all-users').prop('checked', false);
    });

});
