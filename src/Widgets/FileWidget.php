<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A relationship to one stored file (a poster, a logo): a preview of the
 * current file, an upload, a picker over existing files and a remove
 * checkbox. An upload wins over the picker and the checkbox.
 */
class FileWidget extends BaseWidget implements ProvidesOptions
{
    protected $view = 'charonfrontend::widgets.file';

    /**
     * @var UploadHandler
     */
    private $handler;

    /**
     * @var string
     */
    private $accept;

    /**
     * @param UploadHandler $handler
     * @param string $accept Accepted mime types, as in the accept attribute.
     */
    public function __construct(UploadHandler $handler, string $accept = 'image/*')
    {
        $this->handler = $handler;
        $this->accept = $accept;
    }

    public function isMultipart(): bool
    {
        return true;
    }

    public function getOptions(Request $request, FormField $field): array
    {
        return $this->handler->getOptions($request, $field);
    }

    protected function getViewData(FormField $field): array
    {
        $current = $field->getValue();

        $options = $field->getOptions();
        if ($current !== null && !array_key_exists($current, $options)) {
            $options = [ $current => '#' . $current ] + $options;
        }

        return [
            'current' => $current,
            'previewUrl' => $current !== null ? $this->handler->getPreviewUrl($current) : null,
            'options' => $options,
            'selected' => (string) $field->old('id', $current),
            'accept' => $this->accept,
        ];
    }

    public function read(Request $request, FormField $field)
    {
        $file = $request->file($field->getDotName('file'));
        if ($file instanceof UploadedFile) {
            $validator = Validator::make(
                [ 'file' => $file ],
                [ 'file' => $this->getValidationRules() ],
                [ 'file.mimetypes' => $field->getLabel() . ': this type of file is not accepted.' ]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    $field->getDotName('file') => $validator->errors()->get('file'),
                ]);
            }

            return [ 'id' => $this->handler->store($file, $field) ];
        }

        if ($this->input($request, $field, 'remove')) {
            return null;
        }

        $id = $this->nullIfEmpty($this->input($request, $field, 'id'));
        return $id === null ? null : [ 'id' => $id ];
    }

    /**
     * @return array
     */
    protected function getValidationRules(): array
    {
        $rules = [ 'file' ];

        $types = array_filter(array_map('trim', explode(',', $this->accept)));
        if (count($types) > 0 && !in_array('*/*', $types, true)) {
            $rules[] = 'mimetypes:' . implode(',', $types);
        }

        return $rules;
    }
}
