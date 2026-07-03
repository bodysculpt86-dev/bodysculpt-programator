<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="whatsapp-qr-page">
    <h4 class="mb-3 fw-light"><?= lang('whatsapp_qr') ?></h4>

    <div class="card">
        <div class="card-body">
            <p>
                <?= lang('whatsapp') ?> —
                <span id="wa-status" class="badge bg-secondary"><?= lang('loading') ?></span>
            </p>

            <button id="wa-connect-btn" class="btn btn-success">
                <i class="fab fa-whatsapp"></i> <?= lang('connect') ?> WhatsApp
            </button>

            <div id="wa-qr-container" class="mt-3" style="display:none;">
                <p class="text-muted"><?= lang('scan_qr_hint') ?></p>
                <img id="wa-qr-img" alt="QR" style="max-width:280px;border:1px solid #ddd;padding:8px;border-radius:8px;">
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script>
(function () {
    const statusUrl  = <?= json_encode(site_url('whatsapp_qr/status')) ?>;
    const qrUrl      = <?= json_encode(site_url('whatsapp_qr/qr')) ?>;
    const badge      = document.getElementById('wa-status');
    const btn        = document.getElementById('wa-connect-btn');
    const qrBox      = document.getElementById('wa-qr-container');
    const qrImg      = document.getElementById('wa-qr-img');
    let pollTimer    = null;

    function renderState(state) {
        if (state === 'open') {
            badge.className = 'badge bg-success';
            badge.textContent = 'Conectat';
            qrBox.style.display = 'none';
            btn.style.display = 'none';
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        } else if (state === 'connecting') {
            badge.className = 'badge bg-warning text-dark';
            badge.textContent = 'Se conecteaza...';
        } else {
            badge.className = 'badge bg-secondary';
            badge.textContent = 'Deconectat';
        }
    }

    function checkStatus() {
        fetch(statusUrl).then(r => r.json()).then(d => renderState(d.state)).catch(() => {});
    }

    function getQr() {
        btn.disabled = true;
        fetch(qrUrl).then(r => r.json()).then(d => {
            btn.disabled = false;
            if (d.base64) {
                qrImg.src = d.base64.startsWith('data:') ? d.base64 : ('data:image/png;base64,' + d.base64);
                qrBox.style.display = 'block';
                // pornim polling ca sa detectam conectarea
                if (!pollTimer) pollTimer = setInterval(checkStatus, 3000);
            } else {
                alert('Nu s-a putut genera QR. Verifica logurile.');
            }
        }).catch(() => { btn.disabled = false; });
    }

    btn.addEventListener('click', getQr);
    checkStatus(); // stare initiala la incarcarea paginii
})();
</script>
<?php end_section('scripts'); ?>
