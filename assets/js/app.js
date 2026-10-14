(function () {
    'use strict';

    var toggle = document.getElementById('navToggle');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');

    function closeNav() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (backdrop) backdrop.hidden = true;
    }

    function openNav() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (backdrop) backdrop.hidden = false;
    }

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            if (sidebar.classList.contains('open')) {
                closeNav();
            } else {
                openNav();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeNav);
    }

    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            var message = el.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-open-modal]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var id = trigger.getAttribute('data-open-modal');
            var modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('open');
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var modal = trigger.closest('.modal');
            if (modal) {
                modal.classList.remove('open');
            }
        });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('open');
            }
        });
    });
})();
