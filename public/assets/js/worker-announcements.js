$(document).ready(function() {
    $(document).on('click', '.worker-announcements-table tbody tr', function () {
        const $preview = $(this).find('.announcement-preview').first();
        if (!$preview.length) return;

        const title = $preview.data('title') || '';
        const body = $preview.data('body') || '';
        const period = $preview.data('period') || '';

        $('#worker-announcement-title').text(title);
        $('#worker-announcement-body').text(body);
        $('#worker-announcement-period').text(period);

        const modalEl = document.getElementById('workerAnnouncementModal');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    });
});
