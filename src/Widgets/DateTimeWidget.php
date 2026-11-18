<?php

namespace CatLab\CharonFrontend\Widgets;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A date and a time input. Both empty means no value.
 */
class DateTimeWidget extends BaseWidget
{
    protected $view = 'charonfrontend::widgets.datetime';

    protected function getViewData(FormField $field): array
    {
        $value = $field->getValue();
        $dateTime = $value ? Carbon::parse($value) : null;

        return [
            'date' => $field->old('date', $dateTime ? $dateTime->format('Y-m-d') : null),
            'time' => $field->old('time', $dateTime ? $dateTime->format('H:i') : null),
        ];
    }

    public function read(Request $request, FormField $field)
    {
        $date = $this->input($request, $field, 'date');
        $time = $this->input($request, $field, 'time');

        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date . ' ' . ($time ?: '00:00'))->format(DATE_RFC822);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                $field->getDotName('date') => __('charonfrontend::crud.invalid_date', [ 'field' => $field->getLabel() ]),
            ]);
        }
    }
}
