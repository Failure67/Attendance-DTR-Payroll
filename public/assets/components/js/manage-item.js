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

    function resetEditForm($manageItem) {
        const manageItemName = $manageItem.data('name') || 'items';
        $manageItem.find(`#item-name-${manageItemName}-manage-item`).val('');
        $manageItem.find(`#item-amount-${manageItemName}-manage-item`).val('');
        $manageItem.find('.manage-item-edit').hide();
        $manageItem.find('.manage-item-more').show();
        $manageItem.find('.new-item').removeClass('update-mode').removeData('index');
        $manageItem.find('.item-option').show();
    }

    $('.manage-item-edit').hide();
    
    updateAddItemLabel();

    $(document).on('click', '.manage-item-more', function() {
        const $manageItem = $(this).closest('.manage-item-option');
        const manageItemName = $manageItem.data('name') || 'items';

        $manageItem.find('.manage-item-edit').show();
        $manageItem.find('.manage-item-more').hide();

        $manageItem.find(`#item-name-${manageItemName}-manage-item`).focus();
    });

    $(document).on('click', '.new-item.add', function() {
        const $button = $(this);
        const $manageItem = $button.closest('.manage-item-option');
        const manageItemName = $manageItem.data('name') || 'items';
        const $nameInput = $manageItem.find(`#item-name-${manageItemName}-manage-item`);
        const $amountInput = $manageItem.find(`#item-amount-${manageItemName}-manage-item`);

        const name = $nameInput.val().trim();
        const amount = $amountInput.val().replace(/,/g, '').trim();

        if (!name) {
            alert('Please enter a valid name.');
            $nameInput.focus();
            return;
        }

        if (!amount || parseFloat(amount) <= 0) {
            alert('Please enter a valid amount.');
            $amountInput.focus();
            return;
        }

        const isUpdateMode = $button.hasClass('update-mode');

        if (isUpdateMode) {
            const index = $button.data('index');
            const $itemOption = $manageItem.find(`.item-option[data-index="${index}"]`);

            $itemOption.find('.item-name').text(name);
            $itemOption.find('.item-amount').text(`₱ ${parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`);
            $itemOption.find(`input[name="${manageItemName}[${index}][name]"]`).val(name);
            $itemOption.find(`input[name="${manageItemName}[${index}][amount]"]`).val(amount);
        } else {
            const newIndex = $manageItem.find('.item-option').length;

            const newItem = $(
                /*html*/`
                    <div class="item-option" data-index="${newIndex}">

                        <div class="item-label">
                            
                            <span class="item-name">${name}</span>
                            
                            |

                            <span class="item-amount">
                                ₱ ${parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                            </span>

                        </div>

                        <input type="hidden" name="${manageItemName}[${newIndex}][name]" value="${name}">

                        <input type="hidden" name="${manageItemName}[${newIndex}][amount]" value="${amount}">

                        <div class="item-action">
            
                            <div class="item-edit">
                                <i class="fa-solid fa-pencil"></i>
                            </div>

                            <div class="item-remove">
                                <i class="fa-solid fa-xmark"></i>
                            </div>

                        </div>

                    </div>

                `
            );

            if (newIndex > 0) $manageItem.find('.manage-item-container').append('<hr>');

            $manageItem.find('.manage-item-container').append(newItem);
        }

        resetEditForm($manageItem);
        updateAddItemLabel();
    });

    $(document).on('click', '.new-item-cancel', function() {
        const $manageItem = $(this).closest('.manage-item-option');
        resetEditForm($manageItem);
    });

    $(document).on('click', '.item-edit', function() {
        const $itemOption = $(this).closest('.item-option');
        const $manageItem = $itemOption.closest('.manage-item-option');
        const manageItemName = $manageItem.data('name') || 'items';
        const index = $itemOption.data('index');

        const name = $itemOption.find('.item-name').text().trim();
        const amount = $itemOption.find(`input[name="${manageItemName}[${index}][amount]"]`).val();

        $manageItem.find(`#item-name-${manageItemName}-manage-item`).val(name);
        $manageItem.find(`#item-amount-${manageItemName}-manage-item`).val(amount);

        $manageItem.find('.new-item').addClass('update-mode').data('index', index);
        $manageItem.find('.manage-item-edit').show();
        $manageItem.find('.manage-item-more').hide();

        $itemOption.hide();
    });

    $(document).on('click', '.item-remove', function() {
        const $itemOption = $(this).closest('.item-option');
        const $manageItem = $itemOption.closest('.manage-item-option');

        $itemOption.prev('hr').remove();
        $itemOption.remove();

        $manageItem.find('.item-option').each(function(newIndex) {
            const manageItemName = $manageItem.data('name') || 'items';
            $(this).attr('data-index', newIndex);
            $(this).find('input[type="hidden"]').each(function() {
                const inputName = $(this).attr('name');
                const newName = inputName.replace(/\[\d+\]/, `[${newIndex}]`);
                $(this).attr('name', newName);
            });
        });

        updateAddItemLabel();
    });
    
    $(document).on('click', '[id$="submit"]', function(e) {
        e.preventDefault();
        const formId = $(this).attr('id').replace('-submit', 'Form');
        const $form = $(`#${formId}`);

        if ($form.length) $form.submit();
    });

});