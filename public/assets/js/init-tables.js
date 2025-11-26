// Initialize tables and fix any display issues
document.addEventListener('DOMContentLoaded', function() {
    // Mark all table containers as initialized after a short delay
    // This will make them visible after the page loads
    setTimeout(function() {
        const tableContainers = document.querySelectorAll('.table-container');
        tableContainers.forEach(container => {
            container.classList.add('initialized');
        });
    }, 50);
});
