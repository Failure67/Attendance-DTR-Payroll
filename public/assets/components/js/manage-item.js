$(document).ready(function() {

    function updateAddItemLabel() {
        $('.manage-item-option').each(function() {
            const $container = $(this);
            const hasItems = $container.find('.item-option').length > 0;

            if (hasItems) {
                $container.find('.manage-label-none').hide();
                $container.find('.manage-label').show();
            } else {
                $container.find('.manage-label-none').show();
                $container.find('.manage-label').hide();
            }
        });
    }

    updateAddItemLabel();

    $(document).on('click', '.manage-item-more', function() {
        const $manageItem = $(this).closest('.manage-item-option');
        const manageItemName = $manageItem.data('name') || 'items';

        $manageItem.find('.manage-item-edit').slideDown(200);
        $manageItem.find('.manage-item-more').hide();

        $manageItem.find(`input[name="temp_${manageItemName}_name"]`).focus();
    });

    $(document).on('click', '.new-item-add:not(.update-mode)', function() {
        const $manageItem = $(this).closest('.manage-item-option');
        const manageItemName = $manageItem.data('name') || 'items';
        const $nameInput = $manageItem.find(`input[name="temp_${manageItemName}_name"]`);
        const $amountInput = $manageItem.find(`input[name="temp_${manageItemName}_amount"]`);

        const name = $nameInput.val().trim();
        const amount = $amountInput.val().replace(/,/g, '').trim();

        if (!name) {
            alert('Please enter a valid name');
            $nameInput.focus();
            return;
        }
        
        if (!amount || parseFloat(amount) <= 0) {
            alert('Please ')
        }
    });

});