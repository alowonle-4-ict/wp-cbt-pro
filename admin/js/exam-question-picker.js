/**
 * Progressive enhancement over the question picker table in exams-form.php
 * (§ Questions & Pools) — every checkbox/pool-key/order field already
 * works as a plain form without this: filtering, "select all visible",
 * and bulk pool-key assignment are all client-side convenience on top of
 * that, for institutions with more questions than fit comfortably in a
 * row-by-row checklist. Inert wherever the picker table isn't present.
 */
(function () {
    'use strict';

    var table = document.querySelector('.wpcbtpro-question-picker');
    if (!table) {
        return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));

    /** ---- Live filter by subject/topic/question text ---- */
    var toolbar = document.createElement('div');
    toolbar.className = 'wpcbtpro-picker-toolbar';

    var filterInput = document.createElement('input');
    filterInput.type = 'search';
    filterInput.className = 'regular-text wpcbtpro-picker-filter';
    filterInput.placeholder = 'Filter by subject, topic, or question text…';
    filterInput.setAttribute('aria-label', 'Filter questions');

    var visibleCountEl = document.createElement('span');
    visibleCountEl.className = 'wpcbtpro-picker-toolbar__count';

    function currentlyVisibleRows() {
        return rows.filter(function (row) {
            return row.style.display !== 'none';
        });
    }

    function updateVisibleCount() {
        visibleCountEl.textContent = currentlyVisibleRows().length + ' of ' + rows.length + ' shown';
    }

    filterInput.addEventListener('input', function () {
        var needle = filterInput.value.trim().toLowerCase();
        rows.forEach(function (row) {
            var haystack = row.dataset.searchText || (row.dataset.searchText = row.textContent.toLowerCase());
            row.style.display = needle === '' || haystack.indexOf(needle) !== -1 ? '' : 'none';
        });
        updateVisibleCount();
    });

    /** ---- Select all currently-visible rows ---- */
    var selectAllLabel = document.createElement('label');
    var selectAllCheckbox = document.createElement('input');
    selectAllCheckbox.type = 'checkbox';
    selectAllLabel.appendChild(selectAllCheckbox);
    selectAllLabel.appendChild(document.createTextNode(' Select all shown'));

    selectAllCheckbox.addEventListener('change', function () {
        currentlyVisibleRows().forEach(function (row) {
            var checkbox = row.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            }
        });
    });

    /** ---- Bulk-assign a pool key + draw target to every checked row ---- */
    var bulkPoolInput = document.createElement('input');
    bulkPoolInput.type = 'text';
    bulkPoolInput.className = 'regular-text wpcbtpro-picker-bulk-pool';
    bulkPoolInput.placeholder = 'pool key';

    var applyButton = document.createElement('button');
    applyButton.type = 'button';
    applyButton.className = 'button';
    applyButton.textContent = 'Apply pool key to checked rows';

    function resetApplyButtonLabelSoon() {
        setTimeout(function () {
            applyButton.textContent = 'Apply pool key to checked rows';
        }, 2000);
    }

    applyButton.addEventListener('click', function () {
        var poolKey = bulkPoolInput.value.trim();
        if (poolKey === '') {
            applyButton.textContent = 'Type a pool key first';
            resetApplyButtonLabelSoon();
            return;
        }

        var applied = 0;
        rows.forEach(function (row) {
            var checkbox = row.querySelector('input[type="checkbox"]');
            var poolField = row.querySelector('input[name*="[pool]"]');
            if (checkbox && checkbox.checked && poolField) {
                poolField.value = poolKey;
                applied++;
            }
        });
        applyButton.textContent = applied > 0 ? 'Applied to ' + applied + ' row(s)' : 'No rows checked';
        resetApplyButtonLabelSoon();
    });

    toolbar.appendChild(filterInput);
    toolbar.appendChild(visibleCountEl);
    toolbar.appendChild(selectAllLabel);
    toolbar.appendChild(bulkPoolInput);
    toolbar.appendChild(applyButton);

    table.parentNode.insertBefore(toolbar, table);
    updateVisibleCount();
})();
