<?php

namespace Tests\Support\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal authenticatable/authorizable model to stand in for a logged-in
 * Eukles admin (the `User` model per CLAUDE.md's naming trap - not to be
 * confused with the ProjectModel rows these Widgets stand in for). Never
 * persisted: FrontCrudController::isMethodAllowed() only needs
 * $request->user()->can(...) to resolve to something Gate can call.
 */
class AdminUser extends Authenticatable
{
    protected $table = 'admin_users';
    protected $guarded = [];
}
