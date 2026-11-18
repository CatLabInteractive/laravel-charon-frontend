<?php

namespace Tests\Support;

use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\UploadHandler;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\Support\Models\Category;

/**
 * "Stores" an upload as a category named after the file.
 */
class FakeUploadHandler implements UploadHandler
{
    public function store(UploadedFile $file, FormField $field)
    {
        return Category::create([ 'name' => $file->getClientOriginalName() ])->id;
    }

    public function discard($id): void
    {
        Category::whereKey($id)->delete();
    }

    public function getPreviewUrl($id): ?string
    {
        return '/preview/' . $id;
    }

    public function getOptions(Request $request, FormField $field): array
    {
        return Category::query()->orderByDesc('id')->pluck('name', 'id')->all();
    }
}
