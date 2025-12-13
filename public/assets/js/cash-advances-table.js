$(document).ready(function() {

    const $search = $('#cash-advances-search');
    const $wrapper = $('.wrapper.cash-advances');
    const isArchivedView = $wrapper.data('archived') === 1 || $wrapper.data('archived') === '1';

    const $ledgerContainer = $('.table-container.cash-advances-table').closest('.container.cash-advances.table-component');
    const $summaryContainer = $('#cash-advances-summary-container');
    const $summaryToggleBtn = $('#employee-balance-cash-advances');
    const $requestsToggleBtn = $('#view-cash-advances');

    const $ledgerView = $('.cash-advances-view-ledger');
    const $requestsView = $('.cash-advances-view-requests');

    const $deleteBtn = $('#delete-cash-advances');
    const $deleteLabel = $deleteBtn.find('.button-label');
    const $deleteIcon = $deleteBtn.find('.button-icon i');

    let showingSummary = false;
    let showingRequests = false;
    const selectedCashAdvanceIds = new Set();

    function getActiveTable() {
        if (showingSummary && $summaryContainer.length) {
            const $summaryTable = $summaryContainer.find('table').first();
            if ($summaryTable.length) {
                return $summaryTable;
            }
        }

        if ($requestsView.length && $requestsView.is(':visible')) {
            const $requestsTable = $requestsView.find('.table-container.cash-advance-requests-table table').first();
            if ($requestsTable.length) {
                return $requestsTable;
            }
        }

        if ($ledgerContainer.length) {
            const $ledgerTable = $ledgerContainer.find('.table-container.cash-advances-table table').first();
            if ($ledgerTable.length) {
                return $ledgerTable;
            }
        }

        return $();
    }

    function applyFilter() {
        const $table = getActiveTable();
        if (!$table.length) return;

        const term = ($search.val() || '').trim().toLowerCase();
        $table.find('tbody tr').each(function() {
            const text = $(this).text().toLowerCase();
            const matches = !term || text.indexOf(term) !== -1;
            $(this).toggle(matches);
        });
    }

    if ($search.length) {
        $search.on('input', function() {
            applyFilter();
        });
    }

    function updateDeleteButtonState() {
        if (!$deleteBtn.length || !$deleteLabel.length) return;

        const count = selectedCashAdvanceIds.size;

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
                $deleteLabel.text('Back to cash advances');
            } else {
                $deleteLabel.text('View archived');
            }
            if ($deleteIcon.length) {
                $deleteIcon.removeClass('fa-trash').addClass('fa-clock-rotate-left');
            }
        }
    }

    // Summary (Employee Balance) toggle
    if ($summaryToggleBtn.length && $summaryContainer.length && $ledgerContainer.length) {
        $summaryContainer.hide();
        $ledgerContainer.show();

        $summaryToggleBtn.on('click', function() {
            showingSummary = !showingSummary;

            if (showingSummary) {
                $summaryContainer.show();
                $ledgerContainer.hide();
                $(this).find('.button-label').text('Transactions');
            } else {
                $summaryContainer.hide();
                $ledgerContainer.show();
                $(this).find('.button-label').text('Employee Balance');
            }

            applyFilter();
        });
    }

    // History vs Requests toggle
    if ($requestsToggleBtn.length && $ledgerView.length && $requestsView.length) {
        // Default to history (ledger)
        $ledgerView.show();
        $requestsView.hide();
        showingRequests = false;

        $requestsToggleBtn.on('click', function() {
            showingRequests = !showingRequests;

            if (showingRequests) {
                $ledgerView.hide();
                $requestsView.show();
                $(this).find('.button-label').text('View history');
            } else {
                $requestsView.hide();
                $ledgerView.show();
                $(this).find('.button-label').text('View requests');
            }

            applyFilter();
        });
    }

    // Row selection on ledger table
    $(document).on('click', '.cash-advances-table tbody tr', function(e) {
        // Ignore clicks on inline archive action buttons
        if ($(e.target).closest('.cash-advances-archive-actions').length) {
            return;
        }

        const $row = $(this);
        const $cell = $row.find('.cash-advance-entry').first();
        const id = $cell.data('cash-advance-id');
        if (!id) return;

        const idStr = String(id);

        if ($row.hasClass('selected')) {
            $row.removeClass('selected');
            selectedCashAdvanceIds.delete(idStr);
        } else {
            $row.addClass('selected');
            selectedCashAdvanceIds.add(idStr);
        }

        updateDeleteButtonState();
    });

    // Delete cash advances or toggle archived view
    $('#delete-cash-advances').on('click', function() {
        const selectedCount = selectedCashAdvanceIds.size;

        // No selection: act as View archived / Back to cash advances toggle
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

        const ids = Array.from(selectedCashAdvanceIds);

        const $modal = $('#deleteCashAdvancesModal');
        $modal.data('cashAdvanceIds', ids);

        if (ids.length === 1) {
            const $row = $('.cash-advances-table').first().find('tbody tr').filter(function() {
                const $cell = $(this).find('.cash-advance-entry').first();
                return String($cell.data('cash-advance-id')) === String(ids[0]);
            }).first();
            const employeeName = $row.find('.cash-advance-entry').text().trim();
            $modal.find('#confirm-item-name').text(`cash advance entry for ${employeeName}?`);
        } else {
            $modal.find('#confirm-item-name').text(`these ${ids.length} cash advance entries?`);
        }

        const modalInstance = new bootstrap.Modal($modal[0]);
        modalInstance.show();
    });

    $('#confirm-delete-cash-advances').on('click', function(e) {
        e.preventDefault();

        const $modal = $('#deleteCashAdvancesModal');
        const ids = $modal.data('cashAdvanceIds') || [];
        if (!ids.length) {
            $modal.modal('hide');
            return;
        }

        const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = isArchivedView ? '/cash-advances?archived=1' : '/cash-advances';
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
            input.name = 'cash_advance_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        if (isArchivedView) {
            const archivedInput = document.createElement('input');
            archivedInput.type = 'hidden';
            archivedInput.name = 'archived';
            archivedInput.value = '1';
            form.appendChild(archivedInput);
        }

        document.body.appendChild(form);
        form.submit();
    });

    if ($search.length && $search.val()) {
        applyFilter();
    }

    updateDeleteButtonState();
});
