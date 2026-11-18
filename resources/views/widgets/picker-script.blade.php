@once
<style>
    .charon-picker { position: relative; }
    .charon-picker-chips { display: flex; flex-wrap: wrap; gap: .375rem; margin-bottom: .375rem; }
    .charon-picker-chips:empty { display: none; }
    .charon-picker-chip { display: inline-flex; align-items: center; padding: .125rem .25rem .125rem .625rem; border-radius: 1rem; background: #e9ecef; font-size: .875rem; line-height: 1.5; }
    .charon-picker-chip button { border: 0; background: transparent; padding: 0 .375rem; margin-left: .125rem; line-height: 1; font-size: 1.125rem; color: #6c757d; cursor: pointer; border-radius: 1rem; }
    .charon-picker-chip button:hover, .charon-picker-chip button:focus { color: #212529; background: #dee2e6; }
    .charon-picker-menu { position: absolute; left: 0; right: 0; z-index: 1000; max-height: 15rem; overflow-y: auto; margin: .125rem 0 0; padding: .25rem 0; list-style: none; background: #fff; border: 1px solid rgba(0,0,0,.15); border-radius: .25rem; box-shadow: 0 .25rem .75rem rgba(0,0,0,.1); }
    .charon-picker-menu li { padding: .375rem .75rem; cursor: pointer; }
    .charon-picker-menu li[aria-selected="true"] { background: #e9ecef; }
    .charon-picker-menu li.charon-picker-none { color: #6c757d; cursor: default; }
</style>
<script>
(function () {
    function normalize(text) {
        return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function enhance(select) {
        if (select.dataset.charonPickerReady) {
            return;
        }
        select.dataset.charonPickerReady = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'charon-picker';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.hidden = true;

        var chips = document.createElement('div');
        chips.className = 'charon-picker-chips';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.id = select.id + '-search';
        input.placeholder = select.dataset.charonPickerPlaceholder || 'Type to add…';
        input.autocomplete = 'off';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', select.id + '-menu');

        var menu = document.createElement('ul');
        menu.className = 'charon-picker-menu';
        menu.id = select.id + '-menu';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;

        wrapper.appendChild(chips);
        wrapper.appendChild(input);
        wrapper.appendChild(menu);

        // The fieldset's legend labels the search box.
        var legend = select.closest('fieldset') && select.closest('fieldset').querySelector('legend');
        if (legend) {
            legend.id = legend.id || select.id + '-legend';
            input.setAttribute('aria-labelledby', legend.id);
        }

        var matches = [];
        var active = -1;

        function renderChips() {
            chips.innerHTML = '';
            Array.prototype.forEach.call(select.options, function (option) {
                if (!option.selected) {
                    return;
                }
                var chip = document.createElement('span');
                chip.className = 'charon-picker-chip';
                chip.appendChild(document.createTextNode(option.text));

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.innerHTML = '&times;';
                remove.setAttribute('aria-label', (select.dataset.charonPickerRemove || 'Remove :item').replace(':item', option.text));
                remove.addEventListener('click', function () {
                    option.selected = false;
                    renderChips();
                    input.focus();
                });

                chip.appendChild(remove);
                chips.appendChild(chip);
            });
        }

        function renderMenu() {
            var words = normalize(input.value).split(/\s+/).filter(Boolean);
            matches = Array.prototype.filter.call(select.options, function (option) {
                if (option.selected) {
                    return false;
                }
                var text = normalize(option.text);
                return words.every(function (word) { return text.indexOf(word) !== -1; });
            });
            active = matches.length ? 0 : -1;

            menu.innerHTML = '';
            if (!matches.length) {
                var none = document.createElement('li');
                none.className = 'charon-picker-none';
                none.textContent = select.dataset.charonPickerEmpty || 'No matches';
                menu.appendChild(none);
            }
            matches.forEach(function (option, index) {
                var item = document.createElement('li');
                item.setAttribute('role', 'option');
                item.id = select.id + '-option-' + index;
                item.textContent = option.text;
                item.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(index);
                });
                menu.appendChild(item);
            });

            highlight();
            menu.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function highlight() {
            Array.prototype.forEach.call(menu.querySelectorAll('[role="option"]'), function (item, index) {
                item.setAttribute('aria-selected', index === active ? 'true' : 'false');
                if (index === active) {
                    input.setAttribute('aria-activedescendant', item.id);
                    item.scrollIntoView({ block: 'nearest' });
                }
            });
            if (active === -1) {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function close() {
            menu.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function choose(index) {
            var option = matches[index];
            if (!option) {
                return;
            }
            option.selected = true;
            input.value = '';
            renderChips();
            renderMenu();
        }

        input.addEventListener('focus', renderMenu);
        input.addEventListener('input', renderMenu);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (menu.hidden) {
                    renderMenu();
                    return;
                }
                if (matches.length) {
                    active = (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
                    highlight();
                }
            } else if (event.key === 'Enter') {
                // Never submit the form from the search box.
                event.preventDefault();
                if (!menu.hidden) {
                    choose(active);
                }
            } else if (event.key === 'Escape') {
                close();
            } else if (event.key === 'Backspace' && input.value === '') {
                var selected = Array.prototype.filter.call(select.options, function (o) { return o.selected; });
                if (selected.length) {
                    selected[selected.length - 1].selected = false;
                    renderChips();
                    renderMenu();
                }
            }
        });

        renderChips();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('select[data-charon-picker]'), enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endonce
