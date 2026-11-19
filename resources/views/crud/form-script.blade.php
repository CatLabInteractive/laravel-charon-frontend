{{-- The save bar of a form: stays in view at the bottom of the window,
     says when there are unsaved changes and asks before leaving the page
     with them. Fields that change outside the form's own inputs (rich text
     editors) dispatch an "input" event on the form. --}}
@once
<style>
    .charon-form-actions { position: sticky; bottom: 0; z-index: 20; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .5rem; margin: 1rem 0 0; padding: .75rem 0; background: #fff; border-top: 1px solid #dee2e6; }
    .charon-form-actions .charon-form-unsaved { margin-right: auto; display: inline-flex; align-items: center; gap: .5rem; color: #6c757d; font-size: .875rem; }
    .charon-form-actions .charon-form-unsaved::before { content: ""; width: .5rem; height: .5rem; border-radius: 50%; background: #c98a1b; }
    .charon-form-actions .charon-form-unsaved[hidden] { display: none; }
    @media (max-width: 575.98px) {
        /* One row of buttons on a phone; the breadcrumbs lead back instead of Cancel. */
        .charon-form-actions { gap: .375rem .5rem; padding: .5rem 0 .625rem; }
        .charon-form-actions .btn { flex: 1 1 0; min-height: 44px; padding-left: .5rem; padding-right: .5rem; font-size: .9375rem; }
        .charon-form-actions .btn-link { display: none; }
        .charon-form-actions .charon-form-unsaved { flex: 1 0 100%; }
        /* Tabs scroll sideways instead of wrapping. */
        .charon-form-tabs { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; }
        .charon-form-tabs .nav-link { white-space: nowrap; }
    }
    .charon-field-group-collapsed > summary { cursor: pointer; }
    .charon-form-aside .charon-field-group { background: #fff; }
</style>
<script>
(function () {
    function watch(form) {
        if (form.hasAttribute('data-charon-form-ready')) {
            return;
        }
        form.setAttribute('data-charon-form-ready', '1');

        var notice = form.querySelector('[data-charon-unsaved]');
        var dirty = false;
        var submitting = false;

        function markDirty(event) {
            // Filters and the like inside the form that are not part of it.
            if (event && event.target && event.target.form && event.target.form !== form) {
                return;
            }
            dirty = true;
            if (notice) {
                notice.hidden = false;
            }
        }

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function () {
            submitting = true;
        });

        window.addEventListener('beforeunload', function (event) {
            if (dirty && !submitting) {
                event.preventDefault();
                event.returnValue = {!! json_encode(__('charonfrontend::crud.leave_unsaved')) !!};
                return event.returnValue;
            }
        });
    }

    function start() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-charon-form]'), watch);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
@endonce
