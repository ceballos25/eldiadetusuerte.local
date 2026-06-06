(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const fd = new FormData();
        fd.append('action', 'config_publica');
        fetch('/front/ajax/web.ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data?.images) return;
                const img = data.data.images;
                const pick = (k) => img[k]?.url || img[k]?.fallback || '';
                const logo = pick('logo_white') || pick('logo');
                if (logo) {
                    document.querySelectorAll('[data-site-logo-white]').forEach(el => { el.src = logo; });
                    document.querySelectorAll('[data-site-logo]').forEach(el => {
                        if (!el.dataset.siteLogoWhite) el.src = pick('logo') || logo;
                    });
                }
                const fav = pick('favicon');
                if (fav) {
                    document.querySelectorAll('link[data-site-favicon]').forEach(l => { l.href = fav; });
                }
            })
            .catch(() => {});
    });
})();
