/*
 * PlainShow Blog behaviour. Everything talks to /api/v1 with the session's
 * CSRF token. UI basics (theme, dropdowns, menus, toasts) come from the kit.
 */
(function () {
    'use strict';

    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function toast(message, kind) {
        if (window.PlainShow && window.PlainShow.toast) window.PlainShow.toast(message, kind);
    }

    /** POST (or other method) to the blog API. Resolves with the JSON body, rejects with a message. */
    function api(path, data, method) {
        var isForm = data instanceof FormData;
        return fetch('/api/v1/' + path.replace(/^\//, ''), {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: Object.assign({ 'X-CSRF-Token': token, 'Accept': 'application/json' }, isForm || !data ? {} : { 'Content-Type': 'application/json' }),
            body: data ? (isForm ? data : JSON.stringify(data)) : undefined
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok || body.ok === false) {
                    throw new Error(body.message || psT('Något gick fel. Försök igen.'));
                }
                return body;
            });
        });
    }

    function get(path) {
        return fetch('/api/v1/' + path.replace(/^\//, ''), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json().then(function (body) { if (!response.ok || body.ok === false) throw new Error(body.message || psT('Kunde inte hämta.')); return body; }); });
    }

    /* Lazy images: <img class="blog-lazy" data-src="..."> */
    var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            load(entry.target);
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '300px' }) : null;
    function load(img) {
        if (!img.dataset.src) return;
        img.addEventListener('load', function () { img.classList.add('is-loaded'); }, { once: true });
        img.addEventListener('error', function () { img.classList.add('is-loaded'); }, { once: true });
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
    }
    function watchImages(root) {
        (root || document).querySelectorAll('img.blog-lazy[data-src]').forEach(function (img) {
            observer ? observer.observe(img) : load(img);
        });
    }

    /* Forms and buttons that ask first: data-confirm="Ta bort inlägget?" */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            var button = form.querySelector('.is-loading');
            if (button) button.classList.remove('is-loading');
        }
    }, true);

    /* Likes: <button data-like="{post id}" data-liked="0|1"> */
    document.addEventListener('click', function (event) {
        var button = event.target instanceof Element ? event.target.closest('[data-like]') : null;
        if (!button) return;
        event.preventDefault();
        var liked = button.dataset.liked === '1';
        var count = button.querySelector('[data-like-count]');
        var icon = button.querySelector('.bx');
        button.disabled = true;
        api('posts/' + button.dataset.like + '/like', null, liked ? 'DELETE' : 'POST').then(function (body) {
            button.dataset.liked = body.liked ? '1' : '0';
            button.classList.toggle('is-liked', body.liked);
            button.setAttribute('aria-pressed', body.liked ? 'true' : 'false');
            if (icon) icon.className = 'bx ' + (body.liked ? 'bxs-heart' : 'bx-heart');
            if (count) count.textContent = body.likes;
        }).catch(function (error) { toast(error.message, 'error'); })
          .finally(function () { button.disabled = false; });
    });

    /* Modals: <button data-modal-open="{id}">, and inside the modal [data-modal-close]. */
    var lastFocus = null;
    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        lastFocus = document.activeElement;
        modal.hidden = false;
        document.documentElement.classList.add('blog-locked');
        var focusable = modal.querySelector('[data-modal-close], button, a, input, textarea');
        if (focusable) focusable.focus();
    }
    function closeModal(modal) {
        modal.hidden = true;
        if (!document.querySelector('.ps-modal-backdrop:not([hidden]), .blog-lightbox:not([hidden])')) document.documentElement.classList.remove('blog-locked');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        var opener = target.closest('[data-modal-open]');
        if (opener) { event.preventDefault(); openModal(opener.getAttribute('data-modal-open')); return; }
        var backdrop = target.closest('.ps-modal-backdrop[id]');
        if (backdrop && backdrop.id !== 'story-upload' && (target === backdrop || target.closest('[data-modal-close]'))) closeModal(backdrop);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.ps-modal-backdrop[id]:not([hidden])').forEach(function (modal) { if (modal.id !== 'story-upload') closeModal(modal); });
    });

    /* Lightbox: <a href="{full image}" data-lightbox="{group}"><img></a> */
    var box = null, group = [], index = 0, touchX = null;
    function buildLightbox() {
        box = document.createElement('div');
        box.className = 'blog-lightbox';
        box.hidden = true;
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-label', psT('Bild'));
        box.innerHTML = '<img class="blog-lightbox__img" alt="">' +
            '<button type="button" class="blog-lightbox__btn blog-lightbox__close" aria-label="Stäng"><i class="bx bx-x"></i></button>' +
            '<button type="button" class="blog-lightbox__btn blog-lightbox__prev" aria-label="Föregående"><i class="bx bx-chevron-left"></i></button>' +
            '<button type="button" class="blog-lightbox__btn blog-lightbox__next" aria-label="Nästa"><i class="bx bx-chevron-right"></i></button>' +
            '<span class="blog-lightbox__count"></span>';
        document.body.appendChild(box);
        box.addEventListener('click', function (event) {
            var t = event.target instanceof Element ? event.target : null;
            if (!t) return;
            if (t.closest('.blog-lightbox__prev')) show(index - 1);
            else if (t.closest('.blog-lightbox__next')) show(index + 1);
            else if (t.closest('.blog-lightbox__close') || t === box) closeLightbox();
        });
        box.addEventListener('touchstart', function (event) { touchX = event.touches[0].clientX; }, { passive: true });
        box.addEventListener('touchend', function (event) {
            if (touchX === null) return;
            var dx = event.changedTouches[0].clientX - touchX;
            touchX = null;
            if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
        });
    }
    function show(i) {
        if (!group.length) return;
        index = (i + group.length) % group.length;
        var img = box.querySelector('.blog-lightbox__img');
        img.src = group[index].getAttribute('href');
        box.querySelector('.blog-lightbox__count').textContent = group.length > 1 ? psT('{index} av {n}', { index: index + 1, n: group.length }) : '';
        box.classList.toggle('is-single', group.length < 2);
    }
    function closeLightbox() {
        box.hidden = true;
        box.querySelector('.blog-lightbox__img').removeAttribute('src');
        if (!document.querySelector('.ps-modal-backdrop:not([hidden])')) document.documentElement.classList.remove('blog-locked');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    document.addEventListener('click', function (event) {
        var link = event.target instanceof Element ? event.target.closest('a[data-lightbox]') : null;
        if (!link || event.metaKey || event.ctrlKey) return;
        event.preventDefault();
        if (!box) buildLightbox();
        group = Array.prototype.slice.call(document.querySelectorAll('a[data-lightbox="' + link.getAttribute('data-lightbox') + '"]'));
        lastFocus = link;
        box.hidden = false;
        document.documentElement.classList.add('blog-locked');
        show(group.indexOf(link));
        box.querySelector('.blog-lightbox__close').focus();
    });
    document.addEventListener('keydown', function (event) {
        if (!box || box.hidden) return;
        if (event.key === 'Escape') closeLightbox();
        else if (event.key === 'ArrowLeft') show(index - 1);
        else if (event.key === 'ArrowRight') show(index + 1);
    });

    /* Deleting a post: <button data-post-delete="{id}" [data-remove="{element id}"]>.
       Removes that element, or leaves the page when there is none. */
    document.addEventListener('click', function (event) {
        var button = event.target instanceof Element ? event.target.closest('[data-post-delete]') : null;
        if (!button || !window.confirm(psT('Ta bort inlägget? Det går inte att ångra.'))) return;
        button.disabled = true;
        api('posts/' + button.dataset.postDelete, null, 'DELETE').then(function (body) {
            var row = button.dataset.remove ? document.getElementById(button.dataset.remove) : null;
            if (!row) { window.location.href = body.redirect || '/blogfeed'; return; }
            row.remove();
            toast(psT('Inlägget togs bort.'));
        }).catch(function (error) { button.disabled = false; toast(error.message, 'error'); });
    });

    /* Text areas that grow with their text: <textarea data-autogrow> */
    document.addEventListener('input', function (event) {
        var area = event.target;
        if (!(area instanceof HTMLTextAreaElement) || !area.hasAttribute('data-autogrow')) return;
        area.style.height = 'auto';
        area.style.height = Math.min(area.scrollHeight + 2, 320) + 'px';
    });

    document.addEventListener('DOMContentLoaded', function () { watchImages(document); });

    window.Blog = { api: api, get: get, toast: toast, watchImages: watchImages, token: token, openModal: openModal, closeModal: closeModal };
})();
