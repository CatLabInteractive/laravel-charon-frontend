<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The uploads of one save (one request). A file widget has to store its
 * upload before the API validates the rest of the form, because the API
 * links resources by identifier. When the save fails, the controller rolls
 * the uploads back: the stored files are discarded again and the uploaded
 * files are kept on local disk, so the form can offer them on the next try
 * instead of asking for them again.
 *
 * Kept files belong to the session that uploaded them and are removed after
 * a day.
 */
class PendingUploads
{
    const SESSION_KEY = 'charonfrontend.kept_uploads';

    const KEEP_SECONDS = 86400;

    /**
     * @var array[] Each: handler, id, file, field (dot name), kept (token or null).
     */
    private $stored = [];

    /**
     * Record a file a widget stored during this save.
     *
     * @param UploadHandler $handler
     * @param mixed $id
     * @param UploadedFile $file
     * @param FormField $field
     * @param string|null $keptToken When the file came from a kept upload.
     */
    public function stored(UploadHandler $handler, $id, UploadedFile $file, FormField $field, ?string $keptToken = null): void
    {
        $this->stored[] = [
            'handler' => $handler,
            'id' => $id,
            'file' => $file,
            'field' => $field->getDotName(),
            'kept' => $keptToken,
        ];
    }

    /**
     * The save worked: the stored files are in use, kept files used for it
     * are no longer needed.
     */
    public function commit(Request $request): void
    {
        foreach ($this->stored as $upload) {
            if ($upload['kept'] !== null) {
                $this->forget($request, $upload['kept']);
            }
        }
        $this->stored = [];
    }

    /**
     * The save failed: discard what was stored and keep the uploaded files
     * for the next try. Each kept file's token is put in the request input
     * as "<field>.kept", so the redirect back flashes it to the form.
     *
     * @param Request $request
     */
    public function rollback(Request $request): void
    {
        $stored = $this->stored;
        $this->stored = [];

        $input = $request->input();
        $changed = false;

        foreach ($stored as $upload) {
            try {
                $upload['handler']->discard($upload['id']);
            } catch (\Throwable $e) {
                report($e);
            }

            $token = $upload['kept'] ?? $this->keep($request, $upload['file']);
            if ($token !== null) {
                Arr::set($input, $upload['field'] . '.kept', $token);
                $changed = true;
            }
        }

        if ($changed) {
            $request->merge($input);
        }
    }

    /**
     * A file this session kept from an earlier try, or null.
     *
     * @param Request $request
     * @param string $token
     * @return UploadedFile|null
     */
    public function keptFile(Request $request, string $token): ?UploadedFile
    {
        $info = $this->keptInfo($request, $token);
        if ($info === null) {
            return null;
        }

        // Test mode: the file was not uploaded in this request, so PHP's
        // is_uploaded_file() check must not apply.
        return new UploadedFile($info['path'], $info['name'], $info['mime'], null, true);
    }

    /**
     * Name, mime type, size and path of a kept file, or null when this
     * session has no such file (any more).
     *
     * @param Request $request
     * @param string $token
     * @return array|null
     */
    public function keptInfo(Request $request, string $token): ?array
    {
        if (!$request->hasSession() || !preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        $info = $request->session()->get(self::SESSION_KEY . '.' . $token);
        if (!is_array($info) || !is_file($info['path'] ?? '')) {
            return null;
        }

        return $info;
    }

    /**
     * @param Request $request
     * @param UploadedFile $file
     * @return string|null
     */
    private function keep(Request $request, UploadedFile $file): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $this->prune();

        $directory = static::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        $token = Str::random(40);
        $path = $directory . DIRECTORY_SEPARATOR . $token;
        if (!@copy($file->getRealPath(), $path)) {
            return null;
        }

        $request->session()->put(self::SESSION_KEY . '.' . $token, [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => filesize($path),
        ]);

        return $token;
    }

    /**
     * @param Request $request
     * @param string $token
     */
    private function forget(Request $request, string $token): void
    {
        $info = $this->keptInfo($request, $token);
        if ($info !== null) {
            @unlink($info['path']);
        }
        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY . '.' . $token);
        }
    }

    /**
     * Remove kept files older than a day, whoever kept them.
     */
    private function prune(): void
    {
        $directory = static::directory();
        if (!is_dir($directory)) {
            return;
        }

        $before = time() - self::KEEP_SECONDS;
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $before) {
                @unlink($path);
            }
        }
    }

    /**
     * @return string
     */
    public static function directory(): string
    {
        return storage_path('app/charonfrontend/kept-uploads');
    }
}
