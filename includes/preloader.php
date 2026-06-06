<div id="preloader-global" class="d-none position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" 
     style="background: rgba(0,0,0,0.6); z-index: 10000;"> <div class="text-center">
        <div class="spinner-border text-warning" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-2 fw-bold text-white text-uppercase shadow-sm">Espera...</p>
    </div>
</div>

<script>
    function showPreloader() {
        const p = document.getElementById('preloader-global');
        if(p) p.classList.remove('d-none');
    }

    function hidePreloader() {
        const p = document.getElementById('preloader-global');
        if(p) p.classList.add('d-none');
    }
</script>
