<?php

namespace Tests\Support\Models;

use CatLab\Charon\Laravel\Database\Model;

/**
 * Minimal Eloquent model used to smoke test FrontCrudController's table/form
 * generation. Deliberately trivial: an id + a name, nothing else.
 */
class Widget extends Model
{
    protected $table = 'widgets';
    protected $guarded = [];
}
