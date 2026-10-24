/* Notiser: remove one, or clear all except admin notices. */
(function () {
    'use strict';

    function update(count) {
        document.querySelectorAll('[data-notification-count], .blog-bell__count').forEach(function (badge) {
            if (count > 0) badge.textContent = count; else badge.remove();
        });
        var left = document.querySelectorAll('.notice').length;
        document.querySelector('[data-notices]').hidden = left === 0;
        document.querySelector('[data-notices-empty]').hidden = left > 0;
        if (!document.querySelector('.notice:not(.notice--admin)')) {
            var clear = document.querySelector('[data-notices-clear]');
            if (clear) clear.remove();
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        var one = target.closest('[data-notice-delete]');
        if (one) {
            one.disabled = true;
            Blog.api('notifications/' + one.dataset.noticeDelete, null, 'DELETE').then(function (body) {
                document.getElementById('notice-' + one.dataset.noticeDelete).remove();
                update(body.count);
            }).catch(function (error) { one.disabled = false; Blog.toast(error.message, 'error'); });
            return;
        }
        var all = target.closest('[data-notices-clear]');
        if (all && window.confirm(psT('Rensa alla notiser?'))) {
            all.disabled = true;
            Blog.api('notifications', null, 'DELETE').then(function (body) {
                document.querySelectorAll('.notice:not(.notice--admin)').forEach(function (row) { row.remove(); });
                update(body.count);
            }).catch(function (error) { all.disabled = false; Blog.toast(error.message, 'error'); });
        }
    });
})();
