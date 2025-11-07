// © 2025 Romar Jabez

$(document).ready(function() {
    $('.select2').each(function() {
        const placeholder = $(this).data('placeholder') || 'Select an option..';
        const $modalParent = $(this).closest('.modal');

        $(this).select2({
            placeholder: placeholder,
            allowClear: true,
            dropdownParent: $modalParent.length ? $modalParent : $(document.body)
        });
    });
});