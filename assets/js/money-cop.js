(function (global) {
    function parsePrecioCOP(value) {
        if (value === null || value === undefined || value === '') return 0;
        if (typeof value === 'number' && Number.isFinite(value)) {
            return Math.round(value);
        }

        const s = String(value).trim();
        if (!s) return 0;
        if (/^\d+$/.test(s)) return parseInt(s, 10) || 0;

        if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
            return parseInt(s.replace(/\./g, ''), 10) || 0;
        }

        if (/^\d+[.,]\d{1,2}$/.test(s)) {
            return Math.round(parseFloat(s.replace(',', '.')));
        }

        const digits = s.replace(/\D/g, '');
        return digits ? (parseInt(digits, 10) || 0) : 0;
    }

    function formatPrecioCOP(value) {
        const n = parsePrecioCOP(value);
        if (!n) return '';
        return n.toLocaleString('es-CO', { maximumFractionDigits: 0, minimumFractionDigits: 0 });
    }

    global.parsePrecioCOP = parsePrecioCOP;
    global.formatPrecioCOP = formatPrecioCOP;
})(typeof window !== 'undefined' ? window : globalThis);
