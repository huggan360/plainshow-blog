/*
 * The post editor: blocks of text and pictures in order, a preview, a draft
 * kept on this device, and an upload with progress. Large pictures are made
 * smaller in the browser first; the server still checks and re-encodes them.
 */
(function () {
    'use strict';

    var MAX_EDGE = 2560;
    var DRAFT_KEY = 'blog-editor-draft';
    var form = document.getElementById('editor');
    if (!form) return;
    var data = JSON.parse(document.getElementById('editor-data').textContent);
    var postId = data.post;
    var list = form.querySelector('[data-editor-blocks]');
    var titleInput = form.querySelector('[name="title"]');
    var publicInput = form.querySelector('[name="is_public"]');
    var fileInput = document.querySelector('[data-editor-file]');
    var drop = document.querySelector('[data-editor-drop]');
    var preview = form.querySelector('[data-editor-preview]');
    var frame = form.querySelector('.editor__frame');
    var statusText = form.querySelector('[data-editor-status-text]');
    var progress = form.querySelector('[data-editor-progress]');
    var submit = form.querySelector('[data-editor-submit]');
    var summary = form.querySelector('[data-editor-summary]');

    var blocks = [];
    var nextId = 1;
    var dirty = false;
    var busy = false;
    var fileTarget = null;

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function iconButton(icon, label, className) {
        var button = el('button', 'ps-btn ps-btn--ghost ps-btn--sm ps-btn--icon ' + (className || ''));
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.innerHTML = '<i class="bx ' + icon + '"></i>';
        return button;
    }
    function setStatus(message, kind) {
        statusText.textContent = message;
        statusText.parentNode.classList.toggle('is-error', kind === 'error');
    }
    function changed() {
        dirty = true;
        renderSummary();
        saveDraft();
    }

    /* ---------- Blocks from the server: neighbouring pictures share one block ---------- */
    data.sections.forEach(function (section) {
        if (section.type === 'text') {
            blocks.push({ id: nextId++, type: 'text', content: section.content || '' });
            return;
        }
        var item = { kind: 'existing', existing: section.existing, url: section.url };
        var last = blocks[blocks.length - 1];
        if (last && last.type === 'image') last.items.push(item);
        else blocks.push({ id: nextId++, type: 'image', items: [item] });
    });
    if (!blocks.length) blocks.push({ id: nextId++, type: 'text', content: '' });

    /* ---------- Draft on this device (new posts only, text only) ---------- */
    var draftTimer = null;
    function saveDraft() {
        if (postId) return;
        window.clearTimeout(draftTimer);
        draftTimer = window.setTimeout(function () {
            var texts = blocks.filter(function (b) { return b.type === 'text' && b.content.trim(); }).map(function (b) { return b.content; });
            try {
                if (!titleInput.value.trim() && !texts.length) localStorage.removeItem(DRAFT_KEY);
                else localStorage.setItem(DRAFT_KEY, JSON.stringify({ title: titleInput.value, texts: texts, at: Date.now() }));
            } catch (e) { /* private mode */ }
        }, 400);
    }
    function clearDraft() { try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* private mode */ } }
    if (!postId) {
        try {
            var draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
            if (draft && (draft.title || (draft.texts && draft.texts.length))) {
                titleInput.value = draft.title || '';
                blocks = (draft.texts.length ? draft.texts : ['']).map(function (text) { return { id: nextId++, type: 'text', content: text }; });
                setStatus(psT('Ditt utkast är återställt. Bilder behöver läggas till igen.'));
            }
        } catch (e) { /* no draft */ }
    }

    /* ---------- Rendering ---------- */
    function render() {
        list.innerHTML = '';
        blocks.forEach(function (block, index) {
            var card = el('div', 'editor-block editor-block--' + block.type);
            card.dataset.block = block.id;
            var tools = el('div', 'editor-block__tools');
            var label = el('span', 'editor-block__label', block.type === 'text' ? psT('Text') : psT('Bilder'));
            var up = iconButton('bx-chevron-up', psT('Flytta upp'));
            var down = iconButton('bx-chevron-down', psT('Flytta ner'));
            var remove = iconButton('bx-trash', psT('Ta bort blocket'), 'editor-block__remove');
            up.disabled = index === 0;
            down.disabled = index === blocks.length - 1;
            up.addEventListener('click', function () { move(index, -1); });
            down.addEventListener('click', function () { move(index, 1); });
            remove.addEventListener('click', function () { removeBlock(index); });
            tools.append(label, up, down, remove);
            card.appendChild(tools);

            if (block.type === 'text') {
                var area = el('textarea', 'editor-block__text');
                area.value = block.content;
                area.rows = 3;
                area.placeholder = index === 0 ? psT('Berätta vad som hänt...') : psT('Fortsätt skriva...');
                area.setAttribute('aria-label', psT('Text'));
                area.addEventListener('input', function () { block.content = area.value; grow(area); changed(); });
                card.appendChild(area);
                window.requestAnimationFrame(function () { grow(area); });
            } else {
                card.appendChild(renderImages(block));
            }
            list.appendChild(card);
        });
        renderSummary();
    }
    function grow(area) {
        area.style.height = 'auto';
        area.style.height = area.scrollHeight + 2 + 'px';
    }
    function renderImages(block) {
        var grid = el('div', 'editor-images');
        block.items.forEach(function (item, index) {
            var tile = el('div', 'editor-image');
            tile.draggable = true;
            var img = el('img');
            img.src = item.url;
            img.alt = '';
            tile.appendChild(img);
            if (item.kind === 'new') tile.appendChild(el('span', 'ps-badge editor-image__new', psT('Ny')));
            var remove = iconButton('bx-x', psT('Ta bort bilden'), 'editor-image__remove');
            remove.addEventListener('click', function () {
                if (item.kind === 'new') URL.revokeObjectURL(item.url);
                block.items.splice(index, 1);
                if (!block.items.length) blocks.splice(blocks.indexOf(block), 1);
                render();
                changed();
            });
            tile.appendChild(remove);
            // Drag to reorder inside the block.
            tile.addEventListener('dragstart', function (event) { event.dataTransfer.setData('text/plain', 'img:' + block.id + ':' + index); tile.classList.add('is-dragging'); });
            tile.addEventListener('dragend', function () { tile.classList.remove('is-dragging'); });
            tile.addEventListener('dragover', function (event) { if (isReorder(event)) { event.preventDefault(); tile.classList.add('is-over'); } });
            tile.addEventListener('dragleave', function () { tile.classList.remove('is-over'); });
            tile.addEventListener('drop', function (event) {
                tile.classList.remove('is-over');
                var raw = event.dataTransfer.getData('text/plain');
                var parts = raw.split(':');
                if (parts[0] !== 'img' || Number(parts[1]) !== block.id) return;
                event.preventDefault();
                event.stopPropagation();
                var from = Number(parts[2]);
                var moved = block.items.splice(from, 1)[0];
                block.items.splice(index, 0, moved);
                render();
                changed();
            });
            grid.appendChild(tile);
        });
        var add = el('button', 'editor-image editor-image--add');
        add.type = 'button';
        add.innerHTML = '<i class="bx bx-plus"></i><span>' + psT('Lägg till') + '</span>';
        add.addEventListener('click', function () { fileTarget = block; fileInput.click(); });
        grid.appendChild(add);
        return grid;
    }
    function isReorder(event) {
        return Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') === -1;
    }
    function renderSummary() {
        var texts = blocks.filter(function (b) { return b.type === 'text' && b.content.trim(); }).length;
        var images = blocks.reduce(function (sum, b) { return sum + (b.type === 'image' ? b.items.length : 0); }, 0);
        summary.textContent = psT('{texts} textblock, {images} bilder', { texts: texts, images: images });
    }
    function move(index, step) {
        var target = index + step;
        if (target < 0 || target >= blocks.length) return;
        var block = blocks.splice(index, 1)[0];
        blocks.splice(target, 0, block);
        render();
        changed();
    }
    function removeBlock(index) {
        var block = blocks[index];
        var hasContent = block.type === 'text' ? block.content.trim() : block.items.length;
        if (hasContent && !window.confirm(psT('Ta bort blocket?'))) return;
        if (block.type === 'image') block.items.forEach(function (item) { if (item.kind === 'new') URL.revokeObjectURL(item.url); });
        blocks.splice(index, 1);
        if (!blocks.length) blocks.push({ id: nextId++, type: 'text', content: '' });
        render();
        changed();
    }

    /* ---------- Adding blocks and pictures ---------- */
    form.querySelectorAll('[data-editor-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.dataset.editorAdd === 'text') {
                blocks.push({ id: nextId++, type: 'text', content: '' });
                render();
                var areas = list.querySelectorAll('textarea');
                areas[areas.length - 1].focus();
            } else {
                fileTarget = null;
                fileInput.click();
            }
        });
    });
    fileInput.addEventListener('change', function () {
        addFiles(Array.prototype.slice.call(fileInput.files), fileTarget);
        fileInput.value = '';
    });
    function addFiles(files, target) {
        files = files.filter(function (file) { return /^image\//.test(file.type) || /\.(heic|heif)$/i.test(file.name); });
        if (!files.length) return;
        if (!target) {
            var last = blocks[blocks.length - 1];
            if (last && last.type === 'image') target = last;
            else if (last && last.type === 'text' && !last.content.trim() && blocks.length > 1) { target = { id: nextId++, type: 'image', items: [] }; blocks[blocks.length - 1] = target; }
            else { target = { id: nextId++, type: 'image', items: [] }; blocks.push(target); }
        }
        setStatus((files.length === 1 ? psT('Förbereder 1 bild...') : psT('Förbereder {n} bilder...', { n: files.length })));
        Promise.all(files.map(shrink)).then(function (prepared) {
            prepared.forEach(function (file) { target.items.push({ kind: 'new', file: file, url: URL.createObjectURL(file) }); });
            render();
            changed();
            setStatus((prepared.length === 1 ? psT('1 bild tillagd.') : psT('{n} bilder tillagda.', { n: prepared.length })));
        });
    }
    /** Pictures larger than MAX_EDGE, or in a format the server can't read, become JPEG. */
    function shrink(file) {
        if (file.type === 'image/gif' || !window.createImageBitmap) return Promise.resolve(file);
        return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bitmap) {
            var scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
            var readable = /^image\/(jpeg|png|webp)$/.test(file.type);
            if (scale === 1 && readable && file.size < 6 * 1024 * 1024) { bitmap.close(); return file; }
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

    /* Drop pictures anywhere on the page. */
    var dragDepth = 0;
    function hasFiles(event) { return event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') !== -1; }
    window.addEventListener('dragenter', function (event) { if (!hasFiles(event) || busy) return; dragDepth++; drop.hidden = false; });
    window.addEventListener('dragleave', function (event) { if (!hasFiles(event)) return; dragDepth = Math.max(0, dragDepth - 1); if (!dragDepth) drop.hidden = true; });
    window.addEventListener('dragover', function (event) { if (hasFiles(event)) event.preventDefault(); });
    window.addEventListener('drop', function (event) {
        if (!hasFiles(event)) return;
        event.preventDefault();
        dragDepth = 0;
        drop.hidden = true;
        if (busy) return;
        var card = event.target instanceof Element ? event.target.closest('[data-block]') : null;
        var target = null;
        if (card) blocks.forEach(function (b) { if (b.id === Number(card.dataset.block) && b.type === 'image') target = b; });
        addFiles(Array.prototype.slice.call(event.dataTransfer.files), target);
    });

    titleInput.addEventListener('input', changed);
    if (publicInput) publicInput.addEventListener('change', function () { dirty = true; });

    /* ---------- Preview ---------- */
    document.querySelectorAll('[data-editor-mode]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var showPreview = tab.dataset.editorMode === 'preview';
            document.querySelectorAll('[data-editor-mode]').forEach(function (other) {
                other.classList.toggle('is-active', other === tab);
                other.setAttribute('aria-selected', other === tab ? 'true' : 'false');
            });
            if (showPreview) renderPreview();
            preview.hidden = !showPreview;
            frame.hidden = showPreview;
        });
    });
    function renderPreview() {
        preview.innerHTML = '';
        var head = el('header', 'post__head');
        head.appendChild(el('h1', 'post__title', titleInput.value.trim() || psT('Utan titel')));
        preview.appendChild(head);
        var body = el('div', 'post__body');
        blocks.forEach(function (block) {
            if (block.type === 'text') {
                if (!block.content.trim()) return;
                var text = el('div', 'ps-reading post__text');
                block.content.trim().split('\n').forEach(function (line, i) {
                    if (i) text.appendChild(document.createElement('br'));
                    text.appendChild(document.createTextNode(line));
                });
                body.appendChild(text);
            } else if (block.items.length) {
                var grid = el('div', 'post__images post__images--' + Math.min(block.items.length, 3));
                block.items.forEach(function (item) {
                    var cell = el('span', 'post__image');
                    var img = el('img');
                    img.src = item.url;
                    img.alt = '';
                    cell.appendChild(img);
                    grid.appendChild(cell);
                });
                body.appendChild(grid);
            }
        });
        if (!body.children.length) body.appendChild(el('p', 'ps-muted', psT('Inget innehåll ännu.')));
        preview.appendChild(body);
    }

    /* ---------- Publishing ---------- */
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (busy) return;
        var title = titleInput.value.trim();
        if (!title) { setStatus(psT('Skriv en titel först.'), 'error'); titleInput.focus(); return; }
        var sections = [];
        var formData = new FormData();
        var uploadCount = 0;
        blocks.forEach(function (block) {
            if (block.type === 'text') {
                if (block.content.trim()) sections.push({ type: 'text', content: block.content });
                return;
            }
            block.items.forEach(function (item) {
                if (item.kind === 'existing') {
                    sections.push({ type: 'image', source: 'existing', existing: item.existing });
                } else {
                    var key = 'u' + uploadCount++;
                    sections.push({ type: 'image', source: 'upload', upload_key: key });
                    formData.append('uploads[' + key + ']', item.file, item.file.name);
                }
            });
        });
        if (!sections.length) { setStatus(psT('Lägg till text eller bilder innan du publicerar.'), 'error'); return; }
        formData.append('title', title);
        formData.append('is_public', publicInput && publicInput.checked ? '1' : '0');
        formData.append('sections_json', JSON.stringify(sections));
        send(formData, uploadCount);
    });
    function send(formData, uploadCount) {
        busy = true;
        submit.disabled = true;
        form.classList.add('is-busy');
        var bar = progress.parentNode;
        bar.hidden = uploadCount === 0;
        progress.style.width = '0%';
        setStatus(uploadCount ? psT('Laddar upp...') : psT('Sparar...'));
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/v1/posts' + (postId ? '/' + postId : ''));
        xhr.setRequestHeader('X-CSRF-Token', Blog.token);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.addEventListener('progress', function (event) {
            if (!event.lengthComputable) return;
            var percent = Math.round(event.loaded / event.total * 100);
            progress.style.width = percent + '%';
            setStatus(percent < 100 ? psT('Laddar upp {percent} %', { percent: percent }) : psT('Bilderna bearbetas...'));
        });
        xhr.addEventListener('load', function () {
            var body = {};
            try { body = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
            if (xhr.status >= 200 && xhr.status < 300 && body.ok) {
                dirty = false;
                clearDraft();
                setStatus(postId ? psT('Sparat.') : psT('Publicerat.'));
                window.location.href = body.redirect;
                return;
            }
            fail(body.message || (xhr.status === 413 ? psT('Bilderna är för stora tillsammans. Försök med färre åt gången.') : psT('Det gick inte att spara. Försök igen.')));
        });
        xhr.addEventListener('error', function () { fail('Ingen anslutning. Försök igen.'); });
        xhr.send(formData);
    }
    function fail(message) {
        busy = false;
        submit.disabled = false;
        form.classList.remove('is-busy');
        progress.parentNode.hidden = true;
        setStatus(message, 'error');
        Blog.toast(message, 'error');
    }

    window.addEventListener('beforeunload', function (event) {
        if (!dirty || busy) return;
        event.preventDefault();
        event.returnValue = '';
    });

    render();
})();
