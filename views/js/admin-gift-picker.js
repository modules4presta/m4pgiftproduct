/**
 * m4pgiftproduct
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */
(function () {
    'use strict';

    var picker = document.querySelector('.js-m4pgiftproduct-picker');
    if (!picker) { return; }

    var search = picker.querySelector('.js-m4pgiftproduct-search');
    var results = picker.querySelector('.js-m4pgiftproduct-results');
    var hidden = picker.querySelector('.js-m4pgiftproduct-id');
    var chosen = picker.querySelector('.js-m4pgiftproduct-chosen');
    // The configuration page URL already carries the back office token,
    // so the lookup reuses it instead of building a second link.
    var url = window.location.href.split('#')[0] + '&ajax=1&action=searchGiftProduct';
    var timer = null;
    var lastQuery = '';

    function clear() {
        results.innerHTML = '';
        results.hidden = true;
    }

    function choose(product) {
        hidden.value = product.id;
        chosen.textContent = product.name + ' (ID ' + product.id + ')';
        search.value = '';
        clear();
    }

    function render(products) {
        results.innerHTML = '';

        if (!products.length) {
            var empty = document.createElement('li');
            empty.className = 'm4pgiftproduct-empty';
            empty.textContent = picker.getAttribute('data-empty-label');
            results.appendChild(empty);
        }

        products.forEach(function (product) {
            var item = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = product.name + (product.reference ? ' — ' + product.reference : '') + ' (ID ' + product.id + ')';
            button.addEventListener('click', function () { choose(product); });
            item.appendChild(button);
            results.appendChild(item);
        });

        results.hidden = false;
    }

    function run() {
        var query = search.value.trim();
        if (query.length < 2 || query === lastQuery) {
            if (query.length < 2) { clear(); }
            return;
        }
        lastQuery = query;

        fetch(url + '&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) { render(data.products || []); })
            .catch(clear);
    }

    search.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(run, 250);
    });

    document.addEventListener('click', function (event) {
        if (!picker.contains(event.target)) { clear(); }
    });
})();
