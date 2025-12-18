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

    // Include/Skip toggle per row (similar to payroll process screen)
    $table.on('click', '.attendance-include-toggle', function () {
        const $btn = $(this);
        const index = $btn.data('index');
        const $hidden = $table.find('input[type="hidden"][name="records[' + index + '][include]"]');
        if (!$hidden.length) {
            return;
        }

        const current = $hidden.val() === '0' ? '0' : '1';
        if (current === '1') {
            $hidden.val('0');
            $btn.removeClass('btn-outline-primary').addClass('btn-outline-secondary');
            $btn.text('Skip');
        } else {
            $hidden.val('1');
            $btn.removeClass('btn-outline-secondary').addClass('btn-outline-primary');
            $btn.text('Include');
        }
    });

    // Confirm before submitting bulk save using styled modal
	const $bulkForm = $wrapper.find('form').filter(function () {
		const method = ($(this).attr('method') || '').toUpperCase();
		return method === 'POST';
	}).first();

	if ($bulkForm.length) {
		$bulkForm.on('submit', function (e) {
			const form = this;
			const dateVal = ($(form).find('input[name="date"]').val() || '').trim();
			const message = dateVal
				? 'Save bulk attendance for ' + dateVal + ' ?'
				: 'Save bulk attendance for all listed employees?';

			e.preventDefault();

			if (typeof window.appConfirm === 'function') {
				window.appConfirm(message).then(function (ok) {
					if (!ok) {
						return;
					}
					form.submit();
				});
			} else {
				if (window.confirm(message)) {
					form.submit();
				}
			}
		});
	}
});
