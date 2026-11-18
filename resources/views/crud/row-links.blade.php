{{-- Clicking a table row opens its show page: the row's view button
     (.table-action-view) is the link, so rows without one stay inert.
     Clicks on links, buttons and form fields inside the row keep their own
     behaviour; ctrl/cmd/middle click open a new tab; selecting text doesn't
     navigate. --}}
@once
<style>
    tr.charon-row-link { cursor: pointer; }
    tr.charon-row-link:hover > td { background-color: rgba(0, 0, 0, .035); }
</style>
<script>
(function () {
    function linkOf(row) {
        return row.querySelector('a.table-action-view');
    }

    function mark() {
        document.querySelectorAll('table tbody tr').forEach(function (row) {
            if (linkOf(row)) {
                row.classList.add('charon-row-link');
            }
        });
    }

    function open(event) {
        var row = event.target.closest('tr.charon-row-link');
        if (!row || event.target.closest('a, button, input, select, textarea, label, .dropdown')) {
            return;
        }
        if (event.type === 'click' && window.getSelection && String(window.getSelection()) !== '') {
            return;
        }
        var link = linkOf(row);
        if (!link) {
            return;
        }
        if (event.ctrlKey || event.metaKey || event.button === 1) {
            window.open(link.href, '_blank');
        } else if (event.type === 'click') {
            window.location.href = link.href;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mark);
    } else {
        mark();
    }
    document.addEventListener('click', open);
    document.addEventListener('auxclick', open);
})();
</script>
@endonce
