$(document).ready(function() {
    const $tableContainer = $('.table-container.attendance-table');
    const $table = $tableContainer.find('table').first();
    const $search = $('#attendance-search');

    let selectedAttendanceId = null;

    // Row selection
    if ($table.length) {
        $table.on('click', 'tbody tr', function() {
            $table.find('tbody tr').removeClass('selected');
            $(this).addClass('selected');

            const $cell = $(this).find('.attendance-employee').first();
            selectedAttendanceId = $cell.data('attendance-id') || null;
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

    const $attendanceModal = $('#attendanceModal');
    const $attendanceForm = $('#attendanceForm');
    const $methodInput = $('#attendance-form-method');

    function resetAttendanceForm() {
        $attendanceForm[0].reset();
        // Default to POST so Laravel treats this as a normal create request
        if ($methodInput.length) {
            $methodInput.val('POST');
        }

        // Reset select2 employee
        const $employeeSelect = $attendanceForm.find('select[name="user_id"]');
        if ($employeeSelect.length) {
            $employeeSelect.val(null).trigger('change');
        }

        // Reset approval checkboxes
        $('#attendance-overtime-approved').prop('checked', false);
        $('#attendance-leave-approved').prop('checked', false);
    }

    // New attendance
    $('#add-attendance').on('click', function() {
        resetAttendanceForm();
        $attendanceForm.attr('action', '/attendance');
        $attendanceModal.find('.modal-title').text('New attendance record');
        const modalInstance = new bootstrap.Modal($attendanceModal[0]);
        modalInstance.show();
    });

    // Edit attendance
    $('#edit-attendance').on('click', function() {
        if (!selectedAttendanceId) {
            alert('Please select a record to edit.');
            return;
        }

        const $row = $table.find('tbody tr.selected');
        const $employeeCell = $row.find('.attendance-employee').first();
        const userId = $employeeCell.data('user-id') || null;
        const date = $row.find('td').eq(1).text().trim();
        const timeIn = $row.find('td').eq(2).text().trim();
        const timeOut = $row.find('td').eq(3).text().trim();
        const statusText = $row.find('td').eq(6).text().trim();
        const overtimeApproved = $employeeCell.data('overtime-approved');
        const leaveApproved = $employeeCell.data('leave-approved');

        resetAttendanceForm();

        $attendanceForm.attr('action', `/attendance/${selectedAttendanceId}`);
        $methodInput.val('PUT');

        if (userId) {
            const $employeeSelect = $attendanceForm.find('select[name="user_id"]');
            $employeeSelect.val(String(userId)).trigger('change');
        }

        if (date) {
            $('#attendance-date').val(date);
        }

        if (timeIn && timeIn !== '—') {
            $('#attendance-time-in').val(timeIn);
        }

        if (timeOut && timeOut !== '—') {
            $('#attendance-time-out').val(timeOut);
        }

        const $statusSelect = $attendanceForm.find('select[name="status"]');
        if (statusText) {
            // Match by label text where possible
            $statusSelect.val($statusSelect.find('option').filter(function() {
                return $(this).text().trim().toLowerCase() === statusText.toLowerCase();
            }).attr('value') || 'Present');
        }

        // Populate approval checkboxes
        if (typeof overtimeApproved !== 'undefined') {
            $('#attendance-overtime-approved').prop('checked', String(overtimeApproved) === '1');
        }
        if (typeof leaveApproved !== 'undefined') {
            $('#attendance-leave-approved').prop('checked', String(leaveApproved) === '1');
        }

        $attendanceModal.find('.modal-title').text('Edit attendance record');

        const modalInstance = new bootstrap.Modal($attendanceModal[0]);
        modalInstance.show();
    });

    // Delete attendance
    $('#delete-attendance').on('click', function() {
        if (!selectedAttendanceId) {
            alert('Please select a record to delete.');
            return;
        }

        const $row = $table.find('tbody tr.selected');
        const employeeName = $row.find('.attendance-employee').text().trim();
        const date = $row.find('td').eq(1).text().trim();

        const $modal = $('#deleteAttendanceModal');
        $modal.data('attendanceId', selectedAttendanceId);
        $modal.find('#confirm-item-name').text(`attendance record for ${employeeName} on ${date}?`);

        const modalInstance = new bootstrap.Modal($modal[0]);
        modalInstance.show();
    });

    $('#confirm-delete-attendance').on('click', function(e) {
        e.preventDefault();

        const $modal = $('#deleteAttendanceModal');
        const id = $modal.data('attendanceId');
        if (!id) {
            $modal.modal('hide');
            return;
        }

        const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/attendance/${id}`;
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

    // Export
    $('#export-attendance').on('click', function() {
        window.location.href = '/attendance/export';
    });
});
