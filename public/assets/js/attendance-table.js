$(document).ready(function() {
    const $detailTableContainer = $('.table-container.attendance-table-detail');
    const $summaryTableContainer = $('.table-container.attendance-table-summary');
    const $detailTable = $detailTableContainer.find('table').first();
    const $summaryTable = $summaryTableContainer.find('table').first();
    const $search = $('#attendance-search');

    const $wrapper = $('.wrapper.attendance');
    const isArchivedView = $wrapper.data('archived') === 1 || $wrapper.data('archived') === '1';
    const currentQueryString = window.location.search || '';

    const $deleteBtn = $('#delete-attendance');
    const $deleteLabel = $deleteBtn.find('.button-label');
    const $deleteIcon = $deleteBtn.find('.button-icon i');

    let selectedAttendanceId = null;
    const selectedAttendanceIds = new Set();

    function isSummaryMode() {
        const $currentSummaryView = $('.attendance-view-summary');
        return $currentSummaryView.length && $currentSummaryView.is(':visible');
    }

    function getActiveTable() {
        if (isSummaryMode()) {
            const $currentSummaryTable = $('.table-container.attendance-table-summary').find('table').first();
            if ($currentSummaryTable.length) {
                return $currentSummaryTable;
            }
        }
        const $currentDetailTable = $('.table-container.attendance-table-detail').find('table').first();
        return $currentDetailTable.length ? $currentDetailTable : $detailTable;
    }

    function loadAttendancePage(urlStr) {
        const url = typeof urlStr === 'string' ? new URL(urlStr, window.location.origin) : urlStr;

        $.get(url.toString(), function (html) {
            const $html = $(html);
            const $newWrapper = $html.find('.wrapper.attendance').first();
            if (!$newWrapper.length) {
                // Fallback: if structure is unexpected, fall back to full navigation
                window.location.href = url.toString();
                return;
            }

            const $currentWrapper = $('.wrapper.attendance').first();

            const $newSummary = $newWrapper.find('.summary').first();
            const $newTableComponent = $newWrapper.find('.table-component').first();
            const $newPagination = $newWrapper.find('.pagination').first();

            if ($newSummary.length) {
                const $currentSummary = $currentWrapper.find('.summary').first();
                if ($currentSummary.length) {
                    $currentSummary.replaceWith($newSummary);
                }
            }

            if ($newTableComponent.length) {
                const $currentTableComponent = $currentWrapper.find('.table-component').first();
                if ($currentTableComponent.length) {
                    $currentTableComponent.replaceWith($newTableComponent);
                }
            }

            if ($newPagination.length) {
                const $currentPagination = $currentWrapper.find('.pagination').first();
                if ($currentPagination.length) {
                    $currentPagination.replaceWith($newPagination);
                }
            }

            // Clear any selection after reload
            selectedAttendanceIds.clear();
            selectedAttendanceId = null;
            updateDeleteButtonState();

            // Ensure we default back to detailed view after any AJAX refresh
            showDetailView();

            // Update browser URL without full reload
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', url.toString());
            }
        });
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
        const $currentDetailView = $('.attendance-view-detail');
        const $currentSummaryView = $('.attendance-view-summary');
        if ($currentDetailView.length) {
            $currentDetailView.show();
        }
        if ($currentSummaryView.length) {
            $currentSummaryView.hide();
        }
        if ($summaryToggleBtn.length) {
            $summaryToggleBtn.find('.button-label').text('Summary view');
        }
    }

    function showSummaryView() {
        const $currentDetailView = $('.attendance-view-detail');
        const $currentSummaryView = $('.attendance-view-summary');
        if ($currentDetailView.length) {
            $currentDetailView.hide();
        }
        if ($currentSummaryView.length) {
            $currentSummaryView.show();
        }
        if ($summaryToggleBtn.length) {
            $summaryToggleBtn.find('.button-label').text('Detail view');
        }
    }

    // Row selection (multi-select toggle) on detailed table only
    $(document).on('click', '.attendance-table-detail tbody tr', function (e) {
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

    // Search filter: server-side search via `search` query param with full page reload (debounced)
    if ($search.length) {
        let searchTimeout = null;

        $search.on('input', function () {
            const term = $(this).val().trim();

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                const url = new URL(window.location.href);
                const params = url.searchParams;

                if (term) {
                    params.set('search', term);
                } else {
                    params.delete('search');
                }

                // Reset to first page when search changes
                params.delete('page');

                url.search = params.toString();
                window.location.href = url.toString();
            }, 400);
        });
    }

    // Let the attendance filter form submit normally (server-side GET + full page reload).
    // Keep a small UX improvement: auto-submit when employee dropdown changes.
    $(document).on('change', '#attendance_filter_employee', function () {
        const form = this.form;
        if (form) {
            form.submit();
        }
    });

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
        $attendanceForm.attr('action', '/attendance' + currentQueryString);
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

        const $row = $('.attendance-table-detail').first().find('tbody tr.selected');
        const $employeeCell = $row.find('.attendance-employee').first();
        const userId = $employeeCell.data('user-id') || null;
        const date = $row.find('td').eq(1).text().trim();

        const timeIn = ($row.find('.attendance-time-in').data('time-24') || '').toString();
        const timeOut = ($row.find('.attendance-time-out').data('time-24') || '').toString();
        const statusText = $row.find('td').eq(6).text().trim();
        const overtimeApproved = $employeeCell.data('overtime-approved');
        const leaveApproved = $employeeCell.data('leave-approved');

        resetAttendanceForm();

        $attendanceForm.attr('action', `/attendance/${selectedId}${currentQueryString}`);
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
        const $row = $('.attendance-table-detail').first().find('tbody tr').filter(function () {
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

        ['employee_id', 'status', 'period_start', 'period_end', 'archived', 'sort_by', 'sort_dir', 'search'].forEach(function (key) {
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

            ['employee_id', 'status', 'period_start', 'period_end', 'archived', 'sort_by', 'sort_dir', 'search'].forEach(function (key) {
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
    
    // AJAX pagination: intercept page links and load partials
    $wrapper.on('click', '.pagination a.page-link', function (e) {
        e.preventDefault();
        const href = $(this).attr('href');
        if (!href) return;
        loadAttendancePage(href);
    });
});
