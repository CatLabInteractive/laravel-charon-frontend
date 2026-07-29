<?php

namespace CatLab\CharonFrontend\Support;

/**
 * Small, dependency-free replacement for the handful of laravelcollective/html
 * FormBuilder/HtmlBuilder behaviours that resources/views/crud/*.blade.php
 * relied on before Task 14.
 *
 * laravelcollective/html has no Laravel 11/12 release (its latest, v6.4.1,
 * caps at illuminate/* ^10.0 - confirmed dead upstream), so this package no
 * longer depends on it (removed from Eukles itself in an earlier platform-
 * modernization step). The crud blades now build raw HTML directly; this
 * class only reproduces the pieces of FormBuilder's behaviour that were
 * load-bearing for old-input repopulation and were not simple 1:1 HTML
 * translations:
 *
 *  - converting bracket-style field names ("fields[foo][input][0][value]")
 *    into the dot path Laravel's old() helper/session old-input array uses
 *    (Collective\Html\FormBuilder::transformKey());
 *  - the checkbox-specific "absent from a redisplayed form means the user
 *    unchecked it" rule (Collective\Html\FormBuilder::getCheckboxCheckedState()),
 *    which is *not* the same as a plain old()-with-default lookup;
 *  - a minimal HTML attribute renderer for the small, flat attribute arrays
 *    ($properties) the blades pass around (Collective\Html\HtmlBuilder::attributes(),
 *    trimmed to the subset actually used here: string/int values and the
 *    'class' key - no numeric/boolean-attribute/array-class/optgroup cases,
 *    since nothing in this package's views ever produced them).
 *
 * Reference for the original behaviour: laravelcollective/html v6.4.1,
 * src/FormBuilder.php and src/HtmlBuilder.php, read from a sibling project's
 * committed vendor/ checkout (the package is not installable against
 * Laravel 12 in this repo, so `composer show` isn't an option) - see
 * task-14-report.md for the exact source excerpts this was verified against.
 */
class FormValue
{
    /**
     * Convert a bracket-style HTML field name into the dot path Laravel's
     * old()/session old-input array uses.
     *
     * Mirrors Collective\Html\FormBuilder::transformKey() exactly, including
     * its quirk of collapsing empty "[]" segments (used for un-indexed
     * multi-value fields like "linkable[tags][][id]") to nothing rather than
     * a numeric index - so, like the original, this does not resolve old
     * input for genuinely-repeated array-style field names. That was already
     * true of the laravelcollective-backed views; not a regression.
     */
    public static function dotName(string $name): string
    {
        return str_replace(['.', '[]', '[', ']'], ['_', '', '.', ''], $name);
    }

    /**
     * old() repopulation for a bracket-style field name: looks up the
     * flashed old-input value at the equivalent dot path, falling back to
     * $default (typically the resource's current value) when there is none.
     *
     * Equivalent to Collective\Html\FormBuilder::getValueAttribute() for the
     * no-model, no-considerRequest case, which is the only case these blades
     * ever used (Form::model() was never called by them).
     */
    public static function old(string $name, $default = null)
    {
        return old(static::dotName($name), $default);
    }

    /**
     * Checkbox checked-state.
     *
     * Reproduces Collective\Html\FormBuilder::getCheckboxCheckedState():
     * if the form is being redisplayed after a failed validation (i.e. some
     * old input was flashed) but this specific checkbox has no old value,
     * the checkbox was left unchecked by the user on that submit - show it
     * unchecked, even if the resource's current value is true. Otherwise
     * prefer the flashed old value, falling back to $default (the resource's
     * current value, on a fresh, non-redisplayed form).
     */
    public static function checkboxChecked(string $name, bool $default): bool
    {
        $dotName = static::dotName($name);
        $oldAll = old();
        $hasOldFlash = is_array($oldAll) && count($oldAll) > 0;
        $oldValue = old($dotName);

        if ($hasOldFlash && is_null($oldValue)) {
            return false;
        }

        if (!is_null($oldValue)) {
            return (bool) $oldValue;
        }

        return $default;
    }

    /**
     * Render a flat associative array as an HTML attribute string (leading
     * space included, empty string if nothing to render), skipping null
     * values. Trimmed-down equivalent of Collective\Html\HtmlBuilder::attributes()
     * for the attribute shapes actually used by these blades.
     */
    public static function attributes(array $attributes): string
    {
        $html = [];

        foreach ($attributes as $key => $value) {
            if (is_null($value)) {
                continue;
            }

            if (is_bool($value)) {
                if ($value) {
                    $html[] = $key;
                }
                continue;
            }

            $html[] = $key . '="' . e($value) . '"';
        }

        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }

    /**
     * Label text, replicating Collective\Html\FormBuilder::formatLabel():
     * fall back to a title-cased version of the field/for-name when no
     * explicit label value is given (or it's falsy).
     */
    public static function labelText(string $forName, $value = null): string
    {
        return $value ?: ucwords(str_replace('_', ' ', $forName));
    }
}
