/* The post page: comments. */
(function () {
    'use strict';

    var list = document.querySelector('[data-comment-list]');
    var empty = document.querySelector('[data-comment-empty]');
    var counters = document.querySelectorAll('[data-comment-count]');

    function recount() {
        var count = list ? list.children.length : 0;
        counters.forEach(function (counter) { counter.textContent = count; });
        if (empty) empty.hidden = count > 0;
    }

    document.querySelectorAll('[data-comment-form]').forEach(function (form) {
        var text = form.querySelector('textarea');
        text.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) form.requestSubmit();
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var button = form.querySelector('[type="submit"]');
            var data = {};
            new FormData(form).forEach(function (value, key) { data[key] = value; });
            button.disabled = true;
            Blog.api(form.dataset.endpoint, data).then(function (body) {
                list.insertAdjacentHTML('beforeend', body.html);
                var added = document.getElementById('comment-' + body.id);
                if (added) { Blog.watchImages(added); added.classList.add('is-new'); }
                text.value = '';
                text.style.height = '';
                recount();
            }).catch(function (error) { Blog.toast(error.message, 'error'); })
              .finally(function () { button.disabled = false; });
        });
    });

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        var remove = target.closest('[data-comment-delete]');
        if (remove) {
            if (!window.confirm(psT('Radera kommentaren?'))) return;
            remove.disabled = true;
            Blog.api('comments/' + remove.dataset.commentDelete, null, 'DELETE').then(function () {
                var item = document.getElementById('comment-' + remove.dataset.commentDelete);
                if (item) item.remove();
                recount();
            }).catch(function (error) { remove.disabled = false; Blog.toast(error.message, 'error'); });
        }
    });
})();
