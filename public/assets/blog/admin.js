/* Admin area: post visibility, films and notices. */
(function () {
    'use strict';

    /* Post visibility: <button data-visibility="{id}" data-public="0|1"> */
    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        var visibility = target.closest('[data-visibility]');
        if (visibility) {
            var next = visibility.dataset.public === '1' ? 0 : 1;
            visibility.disabled = true;
            Blog.api('posts/' + visibility.dataset.visibility + '/visibility', { public: next }).then(function () {
                visibility.dataset.public = String(next);
                visibility.classList.toggle('ps-badge--green', next === 1);
                visibility.querySelector('.bx').className = 'bx ' + (next ? 'bx-world' : 'bx-lock-alt');
                visibility.querySelector('span').textContent = next ? psT('Offentlig') : psT('Inloggade');
            }).catch(function (error) { Blog.toast(error.message, 'error'); })
              .finally(function () { visibility.disabled = false; });
            return;
        }
        var videoDelete = target.closest('[data-video-delete]');
        if (videoDelete && window.confirm(psT('Radera filmen? Filen tas bort från servern.'))) {
            Blog.api('videos/' + videoDelete.dataset.videoDelete, null, 'DELETE').then(function () {
                document.getElementById('video-' + videoDelete.dataset.videoDelete).remove();
                Blog.toast(psT('Filmen raderades.'));
            }).catch(function (error) { Blog.toast(error.message, 'error'); });
            return;
        }
        if (target.closest('[data-notice-all]')) {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('.admin-recipient input'));
            var all = boxes.every(function (box) { return box.checked; });
            boxes.forEach(function (box) { box.checked = !all; });
            return;
        }
        var noticeDelete = target.closest('[data-notice-delete]');
        if (noticeDelete && window.confirm(psT('Radera notisen för alla mottagare?'))) {
            Blog.api('notices/' + noticeDelete.dataset.noticeDelete, null, 'DELETE').then(function () {
                document.getElementById('notice-group-' + noticeDelete.dataset.noticeDelete).remove();
            }).catch(function (error) { Blog.toast(error.message, 'error'); });
        }
    });

    /* Films: text saves when you leave the field, Publik saves at once. */
    document.addEventListener('change', function (event) {
        var target = event.target;
        if (target.matches && target.matches('[data-video-public]')) {
            Blog.api('videos/' + target.dataset.videoPublic, { is_public: target.checked ? 1 : 0 })
                .then(function () { Blog.toast(target.checked ? psT('Filmen är publik.') : psT('Filmen är dold.')); })
                .catch(function (error) { target.checked = !target.checked; Blog.toast(error.message, 'error'); });
        }
        if (target.matches && target.matches('[data-video-text]')) {
            Blog.api('videos/' + target.dataset.videoText, { content: target.value })
                .then(function () { Blog.toast(psT('Texten sparades.')); })
                .catch(function (error) { Blog.toast(error.message, 'error'); });
        }
    });

    /* Notices */
    var noticeForm = document.querySelector('[data-notice-form]');
    if (noticeForm) {
        noticeForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var users = Array.prototype.slice.call(noticeForm.querySelectorAll('input[name="users"]:checked')).map(function (box) { return Number(box.value); });
            if (!users.length) { Blog.toast(psT('Välj minst en mottagare.'), 'error'); return; }
            Blog.api('notices', { users: users, message: noticeForm.message.value }).then(function () {
                window.location.reload();
            }).catch(function (error) { Blog.toast(error.message, 'error'); });
        });
    }

    /* Film upload in chunks */
    var videoForm = document.querySelector('[data-video-form]');
    if (!videoForm) return;
    var settings = JSON.parse(document.getElementById('admin-data').textContent);
    var fileInput = videoForm.querySelector('[data-video-file]');
    var progress = videoForm.querySelector('[data-video-progress]');
    var bar = videoForm.querySelector('[data-video-bar]');
    var status = videoForm.querySelector('[data-video-status]');
    var busy = false;
    fileInput.addEventListener('change', function () {
        var file = fileInput.files[0];
        videoForm.querySelector('[data-video-name]').textContent = file ? file.name + ' (' + Math.round(file.size / 1048576) + ' MB)' : psT('Välj en film');
    });
    videoForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var file = fileInput.files[0];
        if (!file || busy) return;
        busy = true;
        var submit = videoForm.querySelector('[type="submit"]');
        submit.disabled = true;
        progress.hidden = false;
        var chunk = settings.chunk;
        var total = Math.max(1, Math.ceil(file.size / chunk));
        var uploadId = String(Date.now()) + Math.random().toString(36).slice(2);
        var started = Date.now();
        function part(index) {
            var data = new FormData();
            data.append('upload_id', uploadId.replace(/[^A-Za-z0-9]/g, ''));
            data.append('file_size', String(file.size));
            data.append('chunk_index', String(index));
            data.append('total_chunks', String(total));
            data.append('content', videoForm.content.value);
            data.append('is_public', videoForm.is_public.checked ? '1' : '0');
            data.append('chunk', file.slice(index * chunk, Math.min(file.size, (index + 1) * chunk)), file.name);
            return Blog.api('videos', data).then(function (body) {
                var done = Math.min(file.size, (index + 1) * chunk);
                var speed = done / Math.max(1, (Date.now() - started) / 1000);
                bar.style.width = Math.round(done / file.size * 100) + '%';
                status.textContent = Math.round(done / file.size * 100) + ' %, ' + (speed / 1048576).toFixed(1) + ' MB/s';
                return index + 1 < total ? part(index + 1) : body;
            });
        }
        part(0).then(function () {
            status.textContent = psT('Klart');
            window.location.reload();
        }).catch(function (error) {
            busy = false;
            submit.disabled = false;
            status.textContent = error.message;
            Blog.toast(error.message, 'error');
        });
    });
    window.addEventListener('beforeunload', function (event) { if (busy) { event.preventDefault(); event.returnValue = ''; } });
})();
