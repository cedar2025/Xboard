<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $table = 'v2_plugins';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at'
    ];
}
