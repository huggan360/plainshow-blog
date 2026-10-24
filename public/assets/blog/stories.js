/* Story viewer and upload, plus "Visa fler" on the feed. */
(function () {
    'use strict';
    var viewer = document.getElementById('story-viewer');
    if (!viewer) return;
    var me = Number((document.querySelector('[data-story-add]') || {}).dataset && document.querySelector('[data-story-add]').dataset.storyUser || 0);
    var state = { stories: [], index: 0, timer: null, button: null, own: false };
    var DURATION = 6000;
    var el = function (id) { return document.getElementById(id); };

    function open(button) {
        var userId = button.dataset.storyUser;
        state.button = button;
        state.own = button.hasAttribute('data-story-add');
        Blog.get('stories/users/' + userId).then(function (body) {
            if (!body.stories.length) { if (state.own) openUpload(); return; }
            state.stories = body.stories;
            state.index = Math.max(0, body.stories.findIndex(function (s) { return !s.seen; }));
            el('story-who').textContent = state.own ? psT('Din story') : (button.dataset.storyName || '');
            el('story-bars').innerHTML = body.stories.map(function () { return '<span><i></i></span>'; }).join('');
            viewer.hidden = false;
            document.body.style.overflow = 'hidden';
            show();
        }).catch(function (error) { Blog.toast(error.message, 'error'); });
    }

    function show() {
        var story = state.stories[state.index];
        if (!story) { close(); return; }
        clearTimeout(state.timer);
        el('story-image').src = story.image;
        el('story-caption').textContent = story.caption;
        el('story-caption').hidden = !story.caption;
        el('story-ago').textContent = story.ago;
        var views = el('story-views');
        views.hidden = story.views === null;
        if (story.views !== null) views.querySelector('span').textContent = story.views;
        el('story-delete').hidden = !state.own;
        el('story-bars').querySelectorAll('span').forEach(function (bar, i) {
            bar.className = i < state.index ? 'is-done' : (i === state.index ? 'is-active' : '');
        });
        if (!story.seen) { story.seen = true; Blog.api('stories/' + story.id + '/view').catch(function () {}); }
        state.timer = setTimeout(function () { step(1); }, DURATION);
    }

    function step(delta) {
        state.index += delta;
        if (state.index < 0) state.index = 0;
        if (state.index >= state.stories.length) {
            if (state.button && !state.own) state.button.classList.add('is-viewed');
            close();
            return;
        }
        show();
    }

    function close() {
        clearTimeout(state.timer);
        viewer.hidden = true;
        document.body.style.overflow = '';
    }

    function openUpload() { el('story-upload').hidden = false; }
    function closeUpload() { el('story-upload').hidden = true; }

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        var add = target.closest('[data-story-add]');
        if (add) { add.dataset.storyUser ? open(add) : openUpload(); return; }
        var person = target.closest('[data-story-user]');
        if (person) { open(person); return; }
        var stepper = target.closest('[data-story-step]');
        if (stepper) { step(Number(stepper.dataset.storyStep)); return; }
        if (target.closest('[data-story-close]')) { close(); return; }
        if (target.closest('[data-modal-close]') || target === el('story-upload')) { closeUpload(); return; }
        if (target.closest('#story-delete')) {
            var story = state.stories[state.index];
            if (!story || !window.confirm(psT('Ta bort den här storyn?'))) return;
            Blog.api('stories/' + story.id, null, 'DELETE').then(function () {
                Blog.toast(psT('Storyn är borttagen.'));
                state.stories.splice(state.index, 1);
                if (!state.stories.length) { close(); window.location.reload(); return; }
                el('story-bars').lastElementChild && el('story-bars').lastElementChild.remove();
                show();
            }).catch(function (error) { Blog.toast(error.message, 'error'); });
        }
    });
    document.addEventListener('keydown', function (event) {
        if (viewer.hidden) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowRight') step(1);
        if (event.key === 'ArrowLeft') step(-1);
    });

    // Upload with preview and progress.
    var file = el('story-file');
    file && file.addEventListener('change', function () {
        var picked = file.files[0];
        if (!picked) return;
        var preview = el('story-preview');
        preview.src = URL.createObjectURL(picked);
        preview.hidden = false;
        el('story-hint').hidden = true;
    });
    var form = el('story-form');
    form && form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!file.files[0]) { Blog.toast(psT('Välj en bild först.'), 'error'); return; }
        var data = new FormData(form);
        var xhr = new XMLHttpRequest();
        var submit = el('story-submit');
        var bar = el('story-progress');
        submit.classList.add('is-loading');
        bar.hidden = false;
        xhr.open('POST', '/api/v1/stories');
        xhr.setRequestHeader('X-CSRF-Token', Blog.token);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = function (e) { if (e.lengthComputable) bar.firstElementChild.style.width = Math.round(e.loaded / e.total * 100) + '%'; };
        xhr.onload = function () {
            submit.classList.remove('is-loading');
            var body = {};
            try { body = JSON.parse(xhr.responseText); } catch (e) {}
            if (xhr.status >= 200 && xhr.status < 300 && body.ok) { window.location.reload(); }
            else { Blog.toast(body.message || psT('Uppladdningen misslyckades.'), 'error'); bar.hidden = true; }
        };
        xhr.onerror = function () { submit.classList.remove('is-loading'); Blog.toast(psT('Uppladdningen misslyckades.'), 'error'); };
        xhr.send(data);
    });

    // Visa fler.
    var more = el('feed-more');
    more && more.addEventListener('click', function () {
        more.classList.add('is-loading');
        Blog.get('posts?offset=' + more.dataset.offset).then(function (body) {
            var feed = el('feed');
            var holder = document.createElement('div');
            holder.innerHTML = body.html;
            Array.from(holder.children).forEach(function (child) { feed.appendChild(child); });
            Blog.watchImages(feed);
            if (body.next === null) more.parentElement.remove(); else more.dataset.offset = body.next;
        }).catch(function (error) { Blog.toast(error.message, 'error'); })
          .finally(function () { more.classList.remove('is-loading'); });
    });
})();
