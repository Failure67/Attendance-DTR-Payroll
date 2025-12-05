/**
 * Remove checkboxes from tables
 * This script removes the first column if it contains checkboxes
 */
document.addEventListener('DOMContentLoaded', function() {
    // Function to remove checkboxes from a specific table
    function removeCheckboxesFromTable(table) {
        // Remove checkbox column header
        const header = table.querySelector('thead th:first-child');
        if (header && header.querySelector('input[type="checkbox"]')) {
            header.remove();
        }

        // Remove checkbox cells from each row
        table.querySelectorAll('tbody tr').forEach(row => {
            const firstCell = row.querySelector('td:first-child');
            if (firstCell && firstCell.querySelector('input[type="checkbox"]')) {
                firstCell.remove();
            }
        });
    }

    // Process all tables with the users-table class
    document.querySelectorAll('.users-table, .archived-users-table').forEach(table => {
        // Run immediately
        removeCheckboxesFromTable(table);

        // Also run after a short delay in case DataTables modifies the DOM after page load
        setTimeout(() => {
            removeCheckboxesFromTable(table);
        }, 100);
    });
});
