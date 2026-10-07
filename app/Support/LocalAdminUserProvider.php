<?php

namespace App\Support;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Model;

class LocalAdminUserProvider extends EloquentUserProvider
{
    public function createModel(): Model
    {
        return parent::createModel()->setConnection('sandbox_operational');
    }
}
