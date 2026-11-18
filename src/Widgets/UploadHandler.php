<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Stores files for a FileWidget. The library doesn't know where files live;
 * the application does.
 */
interface UploadHandler
{
    /**
     * Store an uploaded file and return the identifier of the resource it
     * became, to be linked to the relationship.
     *
     * @param UploadedFile $file
     * @param FormField $field
     * @return mixed
     */
    public function store(UploadedFile $file, FormField $field);

    /**
     * Remove a file stored by store() that ended up unused, because the
     * form it came with failed to save. The library keeps the uploaded
     * file itself for the next try; this only undoes store().
     *
     * @param mixed $id
     * @return void
     */
    public function discard($id): void;

    /**
     * Url of a preview image for the linked resource, or null.
     *
     * @param mixed $id
     * @return string|null
     */
    public function getPreviewUrl($id): ?string;

    /**
     * The existing files that can be picked, label by identifier. Newest
     * first is what people expect.
     *
     * @param Request $request
     * @param FormField $field
     * @return string[]
     */
    public function getOptions(Request $request, FormField $field): array;
}
