/* Vinrating: rate, add, edit and remove wines. */
(function () {
    'use strict';

    var rateForm = document.querySelector('[data-wine-rate-form]');
    var wineForm = document.querySelector('[data-wine-form]');
    var rateId = null;

    if (rateForm) {
        var range = rateForm.rating;
        var value = rateForm.querySelector('[data-wine-rate-value]');
        range.addEventListener('input', function () { value.textContent = String(range.value).replace('.', ','); });
        rateForm.addEventListener('submit', function (event) {
            event.preventDefault();
            Blog.api('wines/' + rateId + '/rating', { rating: Number(range.value), comment: rateForm.comment.value })
                .then(function () { window.location.reload(); })
                .catch(function (error) { Blog.toast(error.message, 'error'); });
        });
    }
    if (wineForm) {
        wineForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var id = wineForm.id.value;
            var data = new FormData(wineForm);
            data.delete('id');
            if (id) data.delete('image');
            Blog.api(id ? 'wines/' + id : 'wines', data)
                .then(function () { window.location.reload(); })
                .catch(function (error) { Blog.toast(error.message, 'error'); });
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        var rate = target.closest('[data-wine-rate]');
        if (rate) {
            rateId = rate.dataset.wineRate;
            rateForm.querySelector('[data-wine-rate-name]').textContent = rate.dataset.wineName;
            Blog.openModal('wine-rate');
            return;
        }
        var edit = target.closest('[data-wine-edit]');
        if (edit) {
            wineForm.id.value = edit.dataset.wineEdit;
            wineForm.title.value = edit.dataset.title;
            wineForm.description.value = edit.dataset.description;
            wineForm.querySelector('[data-wine-image]').hidden = true;
            document.getElementById('wine-new-title').textContent = psT('Redigera vin');
            Blog.openModal('wine-new');
            return;
        }
        if (target.closest('[data-modal-open="wine-new"]')) {
            wineForm.reset();
            wineForm.id.value = '';
            wineForm.querySelector('[data-wine-image]').hidden = false;
            document.getElementById('wine-new-title').textContent = psT('Lägg till nytt vin');
        }
        var remove = target.closest('[data-wine-delete]');
        if (remove && window.confirm(psT('Ta bort vinet?'))) {
            Blog.api('wines/' + remove.dataset.wineDelete, null, 'DELETE').then(function () {
                document.getElementById('wine-' + remove.dataset.wineDelete).remove();
                Blog.toast(psT('Vinet raderades!'));
            }).catch(function (error) { Blog.toast(error.message, 'error'); });
        }
    });
})();
