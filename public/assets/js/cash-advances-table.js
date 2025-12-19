$(document).ready(function() {

    const $search = $('#cash-advances-search');
    const $wrapper = $('.wrapper.cash-advances');
    const isArchivedView = $wrapper.data('archived') === 1 || $wrapper.data('archived') === '1';
    const requestsOnlyMode = $wrapper.data('requestsOnly') === 1 || $wrapper.data('requestsOnly') === '1';

    const $ledgerContainer = $('.table-container.cash-advances-table').closest('.container.cash-advances.table-component');
    const $summaryContainer = $('#cash-advances-summary-container');
    const $transactionsViewBtn = $('#view-transactions-cash-advances');
    const $balanceViewBtn = $('#view-balance-cash-advances');
    const $requestsViewBtn = $('#view-requests-cash-advances');

    const $ledgerView = $('.cash-advances-view-ledger');
    const $requestsView = $('.cash-advances-view-requests');

    const $deleteBtn = $('#delete-cash-advances');
    const $deleteLabel = $deleteBtn.find('.button-label');
    const $deleteIcon = $deleteBtn.find('.button-icon i');

    let showingSummary = false;
    let showingRequests = false;
    let currentView = 'ledger';
    const selectedCashAdvanceIds = new Set();

    // Determine which table is currently active for filtering. Instead of
    // relying only on internal flags, prefer whichever table container is
    // actually visible. This makes the search work even for requests-only
    // roles (Manager/HR) where there is no ledger container.
    function getActiveTable() {
        // 1) Summary container, if present and visible
        if ($summaryContainer.length && $summaryContainer.is(':visible')) {
            const $summaryTable = $summaryContainer.find('table').first();
            if ($summaryTable.length) {
                return $summaryTable;
            }
        }

        // 2) Requests view, if present and visible
        if ($requestsView.length && $requestsView.is(':visible')) {
            const $requestsTable = $requestsView.find('.table-container.cash-advance-requests-table table').first();
            if ($requestsTable.length) {
                return $requestsTable;
            }
        }

        // 3) Ledger container as a fallback
        if ($ledgerContainer.length && $ledgerContainer.is(':visible')) {
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

    // View dropdown: Transactions / Employee Balance / Requests
    // For Manager/HR (requests-only), there is no ledger container. In that
    // case we skip the view toggle logic entirely and always treat the
    // Requests view as active via getActiveTable().
    if ($ledgerContainer.length && ($transactionsViewBtn.length || $balanceViewBtn.length || $requestsViewBtn.length)) {
        function setView(view, options) {
            const opts = options || {};
            currentView = view;

            if (view === 'summary') {
                showingSummary = true;
                showingRequests = false;

                if ($summaryContainer.length) {
                    $summaryContainer.show();
                }
                if ($ledgerContainer.length) {
                    $ledgerContainer.hide();
                }
            } else {
                showingSummary = false;

                if ($summaryContainer.length) {
                    $summaryContainer.hide();
                }
                if ($ledgerContainer.length) {
                    $ledgerContainer.show();
                }

                if (view === 'requests') {
                    showingRequests = true;
                    if ($ledgerView.length) {
                        $ledgerView.hide();
                    }
                    if ($requestsView.length) {
                        $requestsView.show();
                    }
                } else { // 'ledger' (Transactions)
                    showingRequests = false;
                    if ($requestsView.length) {
                        $requestsView.hide();
                    }
                    if ($ledgerView.length) {
                        $ledgerView.show();
                    }
                }
            }

            // Persist the active view in the URL so a full-page refresh or form
            // POST/redirect keeps you on the same tab (e.g. Requests).
            if (!opts.skipUrlUpdate) {
                try {
                    const url = new URL(window.location.href);
                    const params = url.searchParams;

                    // Default Transactions view does not need an explicit param
                    if (view === 'ledger') {
                        params.delete('ca_view');
                    } else {
                        params.set('ca_view', view);
                    }

                    url.search = params.toString();
                    window.history.replaceState({}, '', url.toString());
                } catch (e) {
                    // ignore URL API errors in very old browsers
                }
            }

            applyFilter();
        }

        // Determine initial view. Requests-only roles (HR/Manager) should land
        // directly on the Requests tab; others can use the ca_view query param.
        let initialView = 'ledger';
        if (requestsOnlyMode) {
            initialView = 'requests';
        } else {
            try {
                const url = new URL(window.location.href);
                const fromQuery = (url.searchParams.get('ca_view') || '').toLowerCase();
                if (fromQuery === 'summary' || fromQuery === 'requests' || fromQuery === 'ledger') {
                    initialView = fromQuery;
                }
            } catch (e) {
                // fall back to default
            }
        }

        setView(initialView, { skipUrlUpdate: true });

        if ($transactionsViewBtn.length) {
            $transactionsViewBtn.on('click', function() {
                // When viewing archived entries, Transactions should return to the
                // main (non-archived) transactions view instead of doing nothing.
                if (isArchivedView) {
                    const url = new URL(window.location.href);
                    const params = url.searchParams;
                    params.delete('archived');
                    url.search = params.toString();
                    window.location.href = url.toString();
                    return;
                }

                setView('ledger');
            });
        }

        if ($balanceViewBtn.length) {
            $balanceViewBtn.on('click', function() {
                setView('summary');
            });
        }

        if ($requestsViewBtn.length) {
            $requestsViewBtn.on('click', function() {
                setView('requests');
            });
        }
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

    // Show details for cash advance requests when clicking a row (except on
    // action buttons/links/forms).
    $(document).on('click', '.cash-advance-requests-table tbody tr', function(e) {
        if ($(e.target).closest('form,button,a,.cash-advance-actions').length) {
            return;
        }

        const $trigger = $(this).find('.ca-request-row-trigger').first();
        if (!$trigger.length) {
            return;
        }

        $('#ca-detail-employee').text($trigger.data('ca-employee') || '—');
        $('#ca-detail-amount').text($trigger.data('ca-amount') || '—');
        $('#ca-detail-status').text($trigger.data('ca-status') || '—');
        $('#ca-detail-requested').text($trigger.data('ca-requested') || '—');
        $('#ca-detail-reason').text($trigger.data('ca-reason') || '—');

        const modalEl = document.getElementById('cashAdvanceRequestDetailsModal');
        if (!modalEl) return;
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    });

    if ($search.length && $search.val()) {
        applyFilter();
    }

    updateDeleteButtonState();
});
