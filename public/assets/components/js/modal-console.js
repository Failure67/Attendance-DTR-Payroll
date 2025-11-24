class ModalConsole {
    constructor(modalId, config ={}) {
        this.$modal = $(`#${modalId}`);
        this.config = {
            currencySymbol: '₱',
            ...config
        };
    }

    formatAmount(value) {
        const number = parseFloat(value) || 0;
        return this.config.currencySymbol + number.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    updateConsole(label, value) {
        this.$modal.find('.console-item').each(function() {
            if ($(this).find('.console-label').text().trim() === label + ':') {
                $(this).find('.console-value').text(value);
            }
        });
    }

    calculateTotal(fieldNames) {
        let total = 0;
        fieldNames.forEach(name => {
            this.$modal.find(`[name="${name}"]`).each(function() {
                const amount = parseFloat($(this).val()) || 0;
                total += amount;
            });
        });
        return total;
    }

    bindField(fieldName, consoleLabel, formatter = null) {
        this.$modal.on('input change', `[name="${fieldName}"]`, (e) => {
            let value = $(e.target).val();
            if (formatter) value = formatter(value);
            else value = value || '';
            this.updateConsole(consoleLabel, value);
        });
    }

    bindSelect(fieldName, consoleLabel) {
        this.$modal.on('change', `[name="${fieldName}"]`, (e) => {
            const selectedText = $(e.target).find('option:selected').text();
            this.updateConsole(consoleLabel, selectedText);
        });
    }

    observeChanges(selector, callback) {
        const container = this.$modal.find(selector)[0];
        if (container) {
            const observer = new MutationObserver(() => {
                callback.call(this);
            });
            observer.observe(container, {
                childList: true,
                subtree: true
            });
        }
    }

    reset(defaults = {}) {
        Object.keys(defaults).forEach(label => {
            this.updateConsole(label, defaults[label]);
        });
    }
    
}
