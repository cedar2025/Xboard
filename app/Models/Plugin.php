<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $version
 * @property string $author
 * @property string $url
 * @property string $email
 * @property string $license
 * @property string $requires
 * @property string $config
 */
class Plugin extends Model
{
    protected $table = 'v2_plugins';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean'
    ];
}
