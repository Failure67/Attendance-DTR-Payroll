$(document).ready(function() {
    const $detailTableContainer = $('.table-container.attendance-table-detail');
    const $summaryTableContainer = $('.table-container.attendance-table-summary');
    const $detailTable = $detailTableContainer.find('table').first();
    const $summaryTable = $summaryTableContainer.find('table').first();
    const $detailView = $('.attendance-view-detail');
    const $summaryView = $('.attendance-view-summary');
    const $search = $('#attendance-search');

    const $wrapper = $('.wrapper.attendance');
    const isArchivedView = $wrapper.data('archived') === 1 || $wrapper.data('archived') === '1';

    const $deleteBtn = $('#delete-attendance');
    const $deleteLabel = $deleteBtn.find('.button-label');
    const $deleteIcon = $deleteBtn.find('.button-icon i');

    let selectedAttendanceId = null;
    const selectedAttendanceIds = new Set();

    function isSummaryMode() {
        return $summaryView.length && $summaryView.is(':visible');
    }

    function getActiveTable() {
        if (isSummaryMode() && $summaryTable.length) {
            return $summaryTable;
        }
        return $detailTable;
    }

    function updateSelectedFromRows() {
        const ids = [];
        if ($detailTable.length) {
            $detailTable.find('tbody tr.selected').each(function () {
                const $cell = $(this).find('.attendance-employee').first();
                const id = $cell.data('attendance-id');
                if (id) {
                    ids.push(String(id));
                }
            });
        }
        selectedAttendanceIds.clear();
        ids.forEach(id => selectedAttendanceIds.add(id));
        selectedAttendanceId = ids.length ? ids[ids.length - 1] : null;
    }

    function updateDeleteButtonState() {
        if (!$deleteBtn.length || !$deleteLabel.length) return;

        const count = selectedAttendanceIds.size;

        if (count > 0) {
            if (isArchivedView) {
                $deleteLabel.text('Delete permanently');
            } else {
                $deleteLabel.text('Delete');
            }
            if ($deleteIcon.length) {
                $deleteIcon.removeClass('fa-clock-rotate-left').addClass('fa-trash');
            }
        } else {
            if (isArchivedView) {
                $deleteLabel.text('Back to attendance');
            } else {
                $deleteLabel.text('View archived');
            }
            if ($deleteIcon.length) {
                $deleteIcon.removeClass('fa-trash').addClass('fa-clock-rotate-left');
            }
        }
    }

    const $summaryToggleBtn = $('#summary-attendance');

    function showDetailView() {
        if ($detailView.length) {
            $detailView.show();
        }
        if ($summaryView.length) {
            $summaryView.hide();
        }
        if ($summaryToggleBtn.length) {
            $summaryToggleBtn.find('.button-label').text('Summary view');
        }
    }

    function showSummaryView() {
        if ($detailView.length) {
            $detailView.hide();
        }
        if ($summaryView.length) {
            $summaryView.show();
        }
        if ($summaryToggleBtn.length) {
            $summaryToggleBtn.find('.button-label').text('Detail view');
        }
    }

    // Row selection (multi-select toggle) on detailed table only
    if ($detailTable.length) {
        $detailTable.on('click', 'tbody tr', function (e) {
            // Ignore clicks on inline action buttons/forms inside archived table
            if ($(e.target).closest('.attendance-archive-actions').length) {
                return;
            }

            const $row = $(this);
            const $cell = $row.find('.attendance-employee').first();
            const id = $cell.data('attendance-id');
            if (!id) return;

            const idStr = String(id);

            if ($row.hasClass('selected')) {
                $row.removeClass('selected');
                selectedAttendanceIds.delete(idStr);
            } else {
                $row.addClass('selected');
                selectedAttendanceIds.add(idStr);
            }

            selectedAttendanceId = selectedAttendanceIds.size ? Array.from(selectedAttendanceIds)[selectedAttendanceIds.size - 1] : null;

            updateDeleteButtonState();
        });
    }

    // Search filter (applies to whichever table is currently visible)
    if ($search.length) {
        $search.on('input', function() {
            const term = $(this).val().trim().toLowerCase();
            const $activeTable = getActiveTable();
            if (!$activeTable.length) return;

            $activeTable.find('tbody tr').each(function() {
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

    // Initialize delete/view-archived button state
    updateDeleteButtonState();
    // Ensure detailed view is visible by default
    showDetailView();

    // Summary/detail toggle
    if ($summaryToggleBtn.length && $summaryTableContainer.length) {
        $summaryToggleBtn.on('click', function() {
            if (isSummaryMode()) {
                showDetailView();
            } else {
                // Clear selection when switching to summary view
                selectedAttendanceIds.clear();
                selectedAttendanceId = null;
                if ($detailTable.length) {
                    $detailTable.find('tbody tr.selected').removeClass('selected');
                }
                updateDeleteButtonState();

                showSummaryView();
            }

            // Re-apply search term to the active table after view switch
            if ($search.length && $search.val().trim() !== '') {
                $search.trigger('input');
            }
        });
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
        if (!selectedAttendanceIds.size) {
            alert('Please select a record to edit.');
            return;
        }

        if (selectedAttendanceIds.size > 1) {
            alert('Please select only one record to edit at a time.');
            return;
        }

        const selectedId = Array.from(selectedAttendanceIds)[0];
        selectedAttendanceId = selectedId;

        const $row = $detailTable.find('tbody tr.selected');
        const $employeeCell = $row.find('.attendance-employee').first();
        const userId = $employeeCell.data('user-id') || null;
        const date = $row.find('td').eq(1).text().trim();

        const timeIn = ($row.find('.attendance-time-in').data('time-24') || '').toString();
        const timeOut = ($row.find('.attendance-time-out').data('time-24') || '').toString();
        const statusText = $row.find('td').eq(6).text().trim();
        const overtimeApproved = $employeeCell.data('overtime-approved');
        const leaveApproved = $employeeCell.data('leave-approved');

        resetAttendanceForm();

        $attendanceForm.attr('action', `/attendance/${selectedId}`);
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

    // Delete attendance or toggle archived view
    $('#delete-attendance').on('click', function() {
        const selectedCount = selectedAttendanceIds.size;

        // No selection: act as View archived / Back to attendance toggle
        if (selectedCount === 0) {
            const url = new URL(window.location.href);
            const params = url.searchParams;

            if (isArchivedView) {
                params.delete('archived');
            } else {
                params.set('archived', '1');
            }

            url.search = params.toString();
            window.location.href = url.toString();
            return;
        }

        const ids = Array.from(selectedAttendanceIds);

        const $modal = $('#deleteAttendanceModal');
        $modal.data('attendanceIds', ids);

        if (ids.length === 1) {
            const $row = $detailTable.find('tbody tr').filter(function () {
                const $cell = $(this).find('.attendance-employee').first();
                return String($cell.data('attendance-id')) === String(ids[0]);
            }).first();
            const employeeName = $row.find('.attendance-employee').text().trim();
            const date = $row.find('td').eq(1).text().trim();
            $modal.find('#confirm-item-name').text(`attendance record for ${employeeName} on ${date}?`);
        } else {
            $modal.find('#confirm-item-name').text(`these ${ids.length} attendance records?`);
        }

        const modalInstance = new bootstrap.Modal($modal[0]);
        modalInstance.show();
    });

    $('#confirm-delete-attendance').on('click', function(e) {
        e.preventDefault();

        const $modal = $('#deleteAttendanceModal');
        const ids = $modal.data('attendanceIds') || [];
        if (!ids.length) {
            $modal.modal('hide');
            return;
        }

        const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = isArchivedView ? '/attendance?archived=1' : '/attendance';
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

        ids.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'attendance_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    });

    // Export (detailed vs summary based on active view)
    $('#export-attendance').on('click', function() {
        const currentUrl = new URL(window.location.href);
        const params = currentUrl.searchParams;

        const basePath = isSummaryMode() ? '/attendance/summary-export' : '/attendance/export';
        let exportUrl = new URL(window.location.origin + basePath);

        ['employee_id', 'status', 'period_start', 'period_end', 'archived', 'sort_by', 'sort_dir'].forEach(function (key) {
            const value = params.get(key);
            if (value !== null && value !== '') {
                exportUrl.searchParams.set(key, value);
            }
        });

        window.location.href = exportUrl.toString();
    });

    // More actions dropdown handlers
    const $moreGenerateDefaults = $('#attendance-more-generate-defaults');
    const $generateDefaultsForm = $('#attendance-generate-defaults-form');
    if ($moreGenerateDefaults.length && $generateDefaultsForm.length) {
        $moreGenerateDefaults.on('click', function () {
            $generateDefaultsForm.submit();
        });
    }

    const $moreImportBtn = $('#attendance-more-import');
    const $importForm = $('#attendance-import-form');
    const $importFileInput = $('#attendance_import_file');
    if ($moreImportBtn.length && $importForm.length && $importFileInput.length) {
        $moreImportBtn.on('click', function () {
            $importFileInput.trigger('click');
        });

        $importFileInput.on('change', function () {
            if (this.files && this.files.length) {
                $importForm.submit();
            }
        });
    }

    const $moreSummaryExport = $('#attendance-more-summary-export');
    if ($moreSummaryExport.length) {
        $moreSummaryExport.on('click', function () {
            const currentUrl = new URL(window.location.href);
            const params = currentUrl.searchParams;

            let exportUrl = new URL(window.location.origin + '/attendance/summary-export');

            ['employee_id', 'status', 'period_start', 'period_end', 'archived', 'sort_by', 'sort_dir'].forEach(function (key) {
                const value = params.get(key);
                if (value !== null && value !== '') {
                    exportUrl.searchParams.set(key, value);
                }
            });

            window.location.href = exportUrl.toString();
        });
    }

    // Column header sorting (server-side via query params) on detailed table only
    if ($detailTable.length) {
        $detailTable.find('thead th[data-sort-key]').on('click', function () {
            const sortKey = $(this).data('sort-key');
            if (!sortKey) return;

            const url = new URL(window.location.href);
            const params = url.searchParams;

            const currentSortBy = params.get('sort_by') || '';
            const currentSortDir = params.get('sort_dir') || 'asc';

            let newDir = 'asc';
            if (currentSortBy === String(sortKey)) {
                newDir = currentSortDir === 'asc' ? 'desc' : 'asc';
            }

            params.set('sort_by', sortKey);
            params.set('sort_dir', newDir);

            url.search = params.toString();
            window.location.href = url.toString();
        });
    }
});
