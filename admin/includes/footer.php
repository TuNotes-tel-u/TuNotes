<?php
/**
 * Footer: closing tag + load modal.js (untuk dialog admin).
 */
?>
</div><!-- /.admin-layout -->

<script src="../assets/js/modal.js"></script>
<script>
// Helper untuk submit form admin lewat hidden form (after modal data input).
// Pakai: submitAdminForm('users.php', 'create', { name: '...', ... });
window.submitAdminForm = function (actionUrl, formAction, data, csrf) {
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = actionUrl;
    function addHidden(name, value) {
        const i = document.createElement('input');
        i.type = 'hidden';
        i.name = name;
        i.value = String(value == null ? '' : value);
        f.appendChild(i);
    }
    addHidden('csrf_token', csrf);
    addHidden('action', formAction);
    if (data && typeof data === 'object') {
        Object.keys(data).forEach(function (k) { addHidden(k, data[k]); });
    }
    document.body.appendChild(f);
    f.submit();
};
</script>
</body>
</html>
