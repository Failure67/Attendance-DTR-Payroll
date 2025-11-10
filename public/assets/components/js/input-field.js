$(document).ready(function() {

    function sanitizeInput(value) {
        if (!value) return '';

        value = value.replace(/[^\d.\-]/g, '');

        let negative = '';
        if (value.startsWith('-')) {
            negative = '-';
            value = value.slice(1);
        }
        value = value.replace(/-/g, '');

        const firstDot = value.indexOf('.');
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
        }
        
        const parts = value.split('.');
        if (parts[1] && parts[1].length > 2) {
            parts[1] = parts[1].slice(0, 2);
            value = parts.join('.');
        }

        if (value.startsWith('.')) value = '0' + value;

        return negative + value;
    }

    function formatAmount(value) {
        if (!value) return '';

        const parts = value.split('.');
        const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        return parts.length > 1 ? integerPart + '.' + parts[1] : integerPart;
    }

    function unformatAmount(value) {
        return value.replace(/,/g, '');
    }
    
    // amount
    $(document).on('input', '.input-field.amount', function () {
        const cursorPos = this.selectionStart;
        const oldLength = this.value.length;

        const unformatted = unformatAmount(this.value);
        const clean = sanitizeInput(unformatted);
        const formatted = formatAmount(clean);

        if (formatted !== this.value) {
            this.value = formatted;

            const diff = formatted.length - oldLength;
            this.setSelectionRange(cursorPos + diff, cursorPos + diff);
        }
    });

    $(document).on('blur', '.input-field.amount', function () {
        let v = unformatAmount(this.value);
        if (v === '-' || v === '.' || v === '-.' || v === '') this.value = '';
        else if (v.endsWith('.')) this.value = formatAmount(v.slice(0, -1)) + '.00';
        else if (v && !v.includes('.')) this.value = formatAmount(v) + '.00';
        else {
            const parts = v.split('.');
            if (parts[1] && parts[1].length === 1) {
                parts[1] = '0' + parts[1];
                v =  parts.join('.');
            }
            this.value = formatAmount(v);
        }
    });

    // number
    $(document).on('input', '.input-field.number', function () {
        const clean = sanitizeInput(this.value);
        if (clean !== this.value) this.value = clean;
    });

    $(document).on('blur', '.input-field.number', function () {
        let v = this.value;
        if (v === '-' || v === '.' || v === '-.') this.value = '';
        else if (v.endsWith('.')) this.value = v.slice(0, -1);
    });
    
});