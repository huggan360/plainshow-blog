/*
 * Albums: create, rename, hide and remove albums; upload photos in chunks
 * with progress; select photos to publish, hide or remove.
 */
(function () {
    'use strict';

    var MAX_EDGE = 3200;
    var dataNode = document.getElementById('album-data');
    var data = dataNode ? JSON.parse(dataNode.textContent) : {};

    function $(selector) { return document.querySelector(selector); }
    function $$(selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); }

    /* ---------- Album list: new album ---------- */
    var createForm = $('[data-album-create]');
    if (createForm) {
        createForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var button = createForm.querySelector('[type="submit"]');
            button.disabled = true;
            Blog.api('albums', { title: createForm.title.value, is_public: createForm.is_public.checked ? 1 : 0 })
                .then(function (body) { window.location.href = body.redirect + '&upload=1'; })
                .catch(function (error) { button.disabled = false; Blog.toast(error.message, 'error'); });
        });
    }
    if (!data.album) return;
    var album = data.album;

    /* ---------- Album settings ---------- */
    var renameForm = $('[data-album-rename]');
    if (renameForm) {
        renameForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var title = renameForm.title.value.trim();
            Blog.api('albums/' + album, { title: title }).then(function () {
                $$('[data-album-title]').forEach(function (node) { node.textContent = title; });
                document.title = title + ' | Week of Bali';
                Blog.closeModal(document.getElementById('album-edit'));
                Blog.toast(psT('Albumet har fått nytt namn.'));
            }).catch(function (error) { Blog.toast(error.message, 'error'); });
        });
    }
    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        var visibility = target.closest('[data-album-visibility]');
        if (visibility) {
            Blog.api('albums/' + album, { is_public: Number(visibility.dataset.albumVisibility) })
                .then(function () { window.location.reload(); })
                .catch(function (error) { Blog.toast(error.message, 'error'); });
        }
        if (target.closest('[data-album-delete]')) {
            var count = $$('[data-photo]').length;
            if (!window.confirm((count ? psT('Ta bort albumet och alla {n} bilder? Det går inte att ångra.', { n: count }) : psT('Ta bort albumet? Det går inte att ångra.')))) return;
            Blog.api('albums/' + album, null, 'DELETE')
                .then(function (body) { window.location.href = body.redirect; })
                .catch(function (error) { Blog.toast(error.message, 'error'); });
        }
    });

    /* ---------- Selecting photos ---------- */
    var grid = $('[data-photo-grid]');
    var bar = $('[data-select-bar]');
    function selected() { return $$('.photo__check input:checked').map(function (box) { return Number(box.value); }); }
    function refreshSelection() {
        var ids = selected();
        $('[data-select-count]').textContent = (ids.length === 1 ? psT('1 vald') : psT('{n} valda', { n: ids.length }));
        $$('[data-select-action]').forEach(function (button) { button.disabled = !ids.length; });
        $$('.photo').forEach(function (photo) { photo.classList.toggle('is-selected', !!photo.querySelector('.photo__check input:checked')); });
    }
    function setSelecting(on) {
        document.body.classList.toggle('is-selecting', on);
        bar.hidden = !on;
        if (!on) $$('.photo__check input').forEach(function (box) { box.checked = false; });
        refreshSelection();
    }
    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        if (target.closest('[data-select-toggle]')) { setSelecting(!document.body.classList.contains('is-selecting')); return; }
        if (target.closest('[data-select-all]')) {
            var boxes = $$('.photo__check input');
            var all = boxes.every(function (box) { return box.checked; });
            boxes.forEach(function (box) { box.checked = !all; });
            refreshSelection();
            return;
        }
        // While selecting, a click on a photo selects it instead of opening it.
        var link = target.closest('.photo__link');
        if (link && document.body.classList.contains('is-selecting')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            var box = link.parentNode.querySelector('.photo__check input');
            if (box) { box.checked = !box.checked; refreshSelection(); }
            return;
        }
        var action = target.closest('[data-select-action]');
        if (action) runAction(action.dataset.selectAction, action);
    }, true);
    if (grid) grid.addEventListener('change', refreshSelection);

    function runAction(kind, button) {
        var ids = selected();
        if (!ids.length) return;
        if (kind === 'delete' && !window.confirm((ids.length === 1 ? psT('Ta bort 1 bild? Det går inte att ångra.') : psT('Ta bort {n} bilder? Det går inte att ångra.', { n: ids.length })))) return;
        button.disabled = true;
        var request = kind === 'delete'
            ? Blog.api('albums/' + album + '/photos/delete', { photos: ids })
            : Blog.api('albums/' + album + '/publish', { photos: ids, published: kind === 'publish' ? 1 : 0 });
        request.then(function () {
            ids.forEach(function (id) {
                var photo = grid.querySelector('[data-photo="' + id + '"]');
                if (!photo) return;
                if (kind === 'delete') { photo.remove(); return; }
                markPublished(photo, kind === 'publish');
            });
            Blog.toast(kind === 'delete' ? psT('Bilderna togs bort.') : kind === 'publish' ? psT('Bilderna är publicerade.') : psT('Bilderna är dolda.'));
            setSelecting(false);
            refreshEmpty();
        }).catch(function (error) { Blog.toast(error.message, 'error'); })
          .finally(function () { button.disabled = false; });
    }
    function markPublished(photo, live) {
        photo.classList.toggle('is-draft', !live);
        var badge = photo.querySelector('.photo__badge');
        if (live && badge) badge.remove();
        if (!live && !badge) {
            badge = document.createElement('span');
            badge.className = 'ps-badge photo__badge';
            badge.textContent = psT('Inte publicerad');
            photo.appendChild(badge);
        }
    }
    function refreshEmpty() {
        var any = !!grid.querySelector('[data-photo]');
        grid.hidden = !any;
        $('[data-photo-empty]').hidden = any;
    }

    /* ---------- Uploading ---------- */
    var uploader = $('[data-uploader]');
    if (!uploader) return;
    var input = uploader.querySelector('[data-upload-input]');
    var dropZone = uploader.querySelector('[data-upload-drop]');
    var list = uploader.querySelector('[data-upload-list]');
    var summary = uploader.querySelector('[data-upload-summary]');
    var statusNode = uploader.querySelector('[data-upload-status]');
    var speedNode = uploader.querySelector('[data-upload-speed]');
    var totalBar = uploader.querySelector('[data-upload-total]');
    var publishNow = uploader.querySelector('[data-upload-publish]');
    var queue = [];
    var running = false;
    var sentBytes = 0;
    var totalBytes = 0;
    var started = 0;

    function openUploader() {
        uploader.hidden = false;
        uploader.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        if (target.closest('[data-upload-open]')) openUploader();
        if (target.closest('[data-upload-close]') && !running) uploader.hidden = true;
    });
    if (data.openUpload) openUploader();

    input.addEventListener('change', function () { add(Array.prototype.slice.call(input.files)); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            if (!event.dataTransfer || Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') === -1) return;
            event.preventDefault();
            uploader.hidden = false;
            dropZone.classList.add('is-over');
        });
    });
    document.addEventListener('dragleave', function (event) { if (!event.relatedTarget) dropZone.classList.remove('is-over'); });
    document.addEventListener('drop', function (event) {
        if (!event.dataTransfer || !event.dataTransfer.files.length) return;
        event.preventDefault();
        dropZone.classList.remove('is-over');
        add(Array.prototype.slice.call(event.dataTransfer.files));
    });

    function add(files) {
        files = files.filter(function (file) { return /^image\//.test(file.type) || /\.(heic|heif)$/i.test(file.name); });
        files.forEach(function (file) {
            var row = document.createElement('li');
            row.className = 'upload-row';
            row.innerHTML = '<span class="upload-row__name"></span><span class="upload-row__state ps-small ps-muted">' + psT('Väntar') + '</span><div class="ps-meter"><span style="width:0%"></span></div>';
            row.querySelector('.upload-row__name').textContent = file.name;
            list.appendChild(row);
            var item = { file: file, row: row, size: file.size };
            queue.push(item);
            totalBytes += file.size;
        });
        if (files.length) { summary.hidden = false; next(); }
    }
    function setRow(item, state, percent, kind) {
        item.row.querySelector('.upload-row__state').textContent = state;
        item.row.classList.toggle('is-error', kind === 'error');
        item.row.classList.toggle('is-done', kind === 'done');
        if (percent !== null) item.row.querySelector('.ps-meter > span').style.width = percent + '%';
    }
    function updateTotals() {
        var percent = totalBytes ? Math.min(100, Math.round(sentBytes / totalBytes * 100)) : 0;
        totalBar.style.width = percent + '%';
        var left = queue.filter(function (item) { return !item.finished; }).length;
        statusNode.textContent = left ? psT('Laddar upp, {left} kvar ({percent} %)', { left: left, percent: percent }) : psT('Klart');
        var seconds = (Date.now() - started) / 1000;
        if (seconds > 1 && left) {
            var speed = sentBytes / seconds;
            var eta = speed > 0 ? Math.max(0, (totalBytes - sentBytes) / speed) : 0;
            speedNode.textContent = psT('{speed} MB/s, {time} kvar', { speed: (speed / 1048576).toFixed(1), time: eta < 60 ? Math.round(eta) + ' s' : Math.round(eta / 60) + ' min' });
        } else {
            speedNode.textContent = '';
        }
    }

    function next() {
        if (running) return;
        var item = queue.find(function (candidate) { return !candidate.finished; });
        if (!item) { finishBatch(); return; }
        running = true;
        if (!started) started = Date.now();
        setRow(item, psT('Förbereder'), 0);
        shrink(item.file).then(function (file) {
            // Count the smaller file from now on.
            totalBytes += file.size - item.size;
            item.size = file.size;
            return send(item, file);
        }).then(function (photo) {
            item.finished = true;
            item.photo = photo;
            setRow(item, psT('Klar'), 100, 'done');
            appendPhoto(photo);
        }).catch(function (error) {
            item.finished = true;
            sentBytes += item.size - (item.sent || 0);
            setRow(item, error.message, null, 'error');
        }).finally(function () {
            running = false;
            updateTotals();
            next();
        });
    }

    function shrink(file) {
        if (file.type === 'image/gif' || !window.createImageBitmap) return Promise.resolve(file);
        return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bitmap) {
            var scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
            var readable = /^image\/(jpeg|png|webp)$/.test(file.type);
            if (scale === 1 && readable && file.size < 12 * 1048576) { bitmap.close(); return file; }
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            bitmap.close();
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    resolve(blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
                }, 'image/jpeg', 0.9);
            });
        }).catch(function () { return file; });
    }

    function send(item, file) {
        var chunk = data.chunk;
        var total = Math.max(1, Math.ceil(file.size / chunk));
        var uploadId = (window.crypto && crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random()).replace(/[^A-Za-z0-9]/g, '').slice(0, 32);
        item.sent = 0;
        function part(index) {
            var blob = file.slice(index * chunk, Math.min(file.size, (index + 1) * chunk));
            var form = new FormData();
            form.append('upload_id', uploadId);
            form.append('file_name', file.name);
            form.append('file_size', String(file.size));
            form.append('chunk_index', String(index));
            form.append('total_chunks', String(total));
            form.append('chunk', blob, file.name);
            return post(form, function (loaded) {
                var done = index * chunk + loaded;
                sentBytes += done - item.sent;
                item.sent = done;
                setRow(item, Math.round(done / file.size * 100) + ' %', Math.round(done / file.size * 100));
                updateTotals();
            }).then(function (body) {
                return index + 1 < total ? part(index + 1) : body.photo;
            });
        }
        return part(0);
    }
    function post(form, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/api/v1/albums/' + album + '/photos');
            xhr.setRequestHeader('X-CSRF-Token', Blog.token);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.addEventListener('progress', function (event) { if (event.lengthComputable) onProgress(event.loaded); });
            xhr.addEventListener('load', function () {
                var body = {};
                try { body = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                if (xhr.status >= 200 && xhr.status < 300 && body.ok) resolve(body);
                else reject(new Error(body.message || (xhr.status === 413 ? psT('Bilden är för stor.') : psT('Uppladdningen misslyckades.'))));
            });
            xhr.addEventListener('error', function () { reject(new Error('Ingen anslutning.')); });
            xhr.send(form);
        });
    }

    function appendPhoto(photo) {
        var figure = document.createElement('figure');
        figure.className = 'photo is-draft';
        figure.dataset.photo = photo.id;
        figure.dataset.own = '1';
        figure.innerHTML = '<a class="photo__link" data-lightbox="album"><img alt=""></a><span class="ps-badge photo__badge">' + psT('Inte publicerad') + '</span><label class="photo__check"><input type="checkbox" aria-label="' + psT('Välj bilden') + '"><span></span></label>';
        figure.querySelector('a').href = photo.full;
        figure.querySelector('img').src = photo.url;
        figure.querySelector('input').value = photo.id;
        grid.insertBefore(figure, grid.firstChild);
        refreshEmpty();
    }

    function finishBatch() {
        var done = queue.filter(function (item) { return item.photo; });
        var failed = queue.filter(function (item) { return item.finished && !item.photo; }).length;
        if (!done.length && !failed) return;
        var after = Promise.resolve();
        if (publishNow && publishNow.checked && done.length) {
            after = Blog.api('albums/' + album + '/publish', { photos: done.map(function (item) { return item.photo.id; }), published: 1 }).then(function () {
                done.forEach(function (item) {
                    var photo = grid.querySelector('[data-photo="' + item.photo.id + '"]');
                    if (photo) markPublished(photo, true);
                });
            });
        }
        after.then(function () {
            Blog.toast((done.length === 1 ? psT('1 bild uppladdad') : psT('{n} bilder uppladdade', { n: done.length })) + (failed ? psT(', {n} misslyckades.', { n: failed }) : '.'), failed ? 'error' : undefined);
        }).catch(function (error) { Blog.toast(error.message, 'error'); });
        queue = [];
        sentBytes = totalBytes = started = 0;
    }

    window.addEventListener('beforeunload', function (event) {
        if (!running && !queue.some(function (item) { return !item.finished; })) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
