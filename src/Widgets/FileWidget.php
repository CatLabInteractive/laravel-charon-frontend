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
 *
 * The upload is stored while the form is read, before the API validates
 * the rest of the form. When the save fails, the front controller discards
 * it again and keeps the file for the next try (see PendingUploads): the
 * form then offers "use the file you uploaded", which wins over the picker.
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

        $kept = null;
        $keptToken = $field->old('kept');
        if (is_string($keptToken)) {
            $info = $this->pendingUploads()->keptInfo(request(), $keptToken);
            if ($info !== null) {
                $kept = [
                    'token' => $keptToken,
                    'name' => $info['name'],
                    'preview' => $this->keptPreview($info),
                ];
            }
        }
        if ($current !== null && !array_key_exists($current, $options)) {
            $options = [ $current => '#' . $current ] + $options;
        }

        return [
            'current' => $current,
            'previewUrl' => $kept !== null
                ? $kept['preview']
                : ($current !== null ? $this->handler->getPreviewUrl($current) : null),
            'options' => $options,
            'selected' => (string) $field->old('id', $current),
            'accept' => $this->accept,
            'kept' => $kept,
        ];
    }

    public function read(Request $request, FormField $field)
    {
        $keptToken = null;
        $file = $request->file($field->getDotName('file'));
        if (!($file instanceof UploadedFile)) {
            $token = $this->input($request, $field, 'kept');
            if (is_string($token) && $token !== '') {
                $file = $this->pendingUploads()->keptFile($request, $token);
                $keptToken = $file ? $token : null;
            }
        }

        if ($file instanceof UploadedFile) {
            $validator = Validator::make(
                [ 'file' => $file ],
                [ 'file' => $this->getValidationRules() ],
                [ 'file.mimetypes' => __('charonfrontend::crud.file_not_accepted', [ 'field' => $field->getLabel() ]) ]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    $field->getDotName('file') => $validator->errors()->get('file'),
                ]);
            }

            $id = $this->handler->store($file, $field);
            $this->pendingUploads()->stored($this->handler, $id, $file, $field, $keptToken);

            return [ 'id' => $id ];
        }

        if ($this->input($request, $field, 'remove')) {
            return null;
        }

        $id = $this->nullIfEmpty($this->input($request, $field, 'id'));
        return $id === null ? null : [ 'id' => $id ];
    }

    /**
     * @return PendingUploads
     */
    protected function pendingUploads(): PendingUploads
    {
        return app(PendingUploads::class);
    }

    /**
     * A data url for a small kept image, so the form can show it.
     * @param array $info
     * @return string|null
     */
    protected function keptPreview(array $info): ?string
    {
        if (strpos((string) $info['mime'], 'image/') !== 0 || $info['size'] > 2 * 1024 * 1024) {
            return null;
        }

        $data = @file_get_contents($info['path']);
        return $data === false ? null : 'data:' . $info['mime'] . ';base64,' . base64_encode($data);
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
