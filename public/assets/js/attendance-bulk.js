$(document).ready(function () {
    const $wrapper = $('.wrapper.attendance-bulk');
    if (!$wrapper.length) {
        return;
    }

    const $tableContainer = $wrapper.find('.table-container.attendance-bulk-table');
    const $table = $tableContainer.find('table').first();

    const $applyBtn = $('#bulk-apply-to-all');
    const $defaultTimeIn = $('#bulk_default_time_in');
    const $defaultTimeOut = $('#bulk_default_time_out');
    const $defaultStatus = $('#bulk_default_status');

    if (!$applyBtn.length || !$table.length) {
        return;
    }

    $applyBtn.on('click', function (e) {
        e.preventDefault();

        const timeIn = ($defaultTimeIn.val() || '').trim();
        const timeOut = ($defaultTimeOut.val() || '').trim();
        const status = ($defaultStatus.val() || '').trim();

        if (!timeIn && !timeOut && status === '') {
            alert('Please enter at least one default value to apply.');
            return;
        }

        $table.find('tbody tr').each(function () {
            const $row = $(this);

            const $timeInInput = $row.find('input[name$="[time_in]"]');
            const $timeOutInput = $row.find('input[name$="[time_out]"]');
            const $statusSelect = $row.find('select[name$="[status]"]');

            if ($timeInInput.length && timeIn) {
                $timeInInput.val(timeIn);
            }

            if ($timeOutInput.length && timeOut) {
                $timeOutInput.val(timeOut);
            }

            if ($statusSelect.length) {
                if (status !== '') {
                    $statusSelect.val(status);
                } else {
                    $statusSelect.val('');
                }
            }
        });
    });
});
