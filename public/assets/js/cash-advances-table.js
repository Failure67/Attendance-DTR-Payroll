$(document).ready(function() {
    const $search = $('#cash-advances-search');
    const $ledgerContainer = $('.table-container.cash-advances-table').closest('.container.cash-advances.table-component');
    const $summaryContainer = $('#cash-advances-summary-container');
    const $toggleButton = $('#employee-balance-cash-advances');

    let showingSummary = false;

    function getActiveTable() {
        let $container = showingSummary && $summaryContainer.length ? $summaryContainer : $ledgerContainer;
        if (!$container || !$container.length) return $();
        return $container.find('table').first();
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

    if ($toggleButton.length && $summaryContainer.length && $ledgerContainer.length) {
        $summaryContainer.hide();
        $ledgerContainer.show();

        $toggleButton.on('click', function() {
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

    if ($search.length && $search.val()) {
        applyFilter();
    }
});
