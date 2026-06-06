<script>
const PaginationHelper = {
    getSegment: function(data, page, limit) {
        const inicio = (page - 1) * limit;
        return data.slice(inicio, inicio + limit);
    },

    render: function(config) {
        const { totalItems, currentPage, limit, containerId, infoId, callbackName } = config;
        const totalPaginas = Math.ceil(totalItems / limit);
        const contenedor = document.getElementById(containerId);
        const info = document.getElementById(infoId);

        if (!contenedor) return;

        if (totalPaginas <= 1) {
            contenedor.innerHTML = '';
            if (info) info.innerText = `Total: ${totalItems} registros`;
            return;
        }

        let html = '';
        const maxVisibles = 5;

        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="${callbackName}(${currentPage - 1})">Ant</a>
                 </li>`;

        let inicio = Math.max(1, currentPage - Math.floor(maxVisibles / 2));
        let fin = Math.min(totalPaginas, inicio + maxVisibles - 1);

        if (fin - inicio + 1 < maxVisibles) {
            inicio = Math.max(1, fin - maxVisibles + 1);
        }

        if (inicio > 1) {
            html += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="${callbackName}(1)">1</a></li>`;
            if (inicio > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }

        for (let i = inicio; i <= fin; i++) {
            html += `<li class="page-item ${currentPage === i ? 'active' : ''}">
                        <a class="page-link" href="javascript:void(0)" onclick="${callbackName}(${i})">${i}</a>
                     </li>`;
        }

        if (fin < totalPaginas) {
            if (fin < totalPaginas - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            html += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="${callbackName}(${totalPaginas})">${totalPaginas}</a></li>`;
        }

        html += `<li class="page-item ${currentPage === totalPaginas ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="${callbackName}(${currentPage + 1})">Sig</a>
                 </li>`;

        contenedor.innerHTML = html;
        if (info) info.innerText = `${currentPage} de ${totalPaginas} (Total: ${totalItems})`;
    }
};
</script>
