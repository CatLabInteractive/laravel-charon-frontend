<?php

namespace Tests\Support\Models;

use CatLab\Charon\Laravel\Database\Model;

/**
 * The "one" side of Widget::category(): exercises relationship cells and
 * their links in the generated tables.
 */
class Category extends Model
{
    protected $table = 'categories';
    protected $guarded = [];
}
