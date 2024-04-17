<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'v2_settings';
    protected $guarded = [];
    protected $casts = [
        'key' => 'string',
        'value' => 'string',
    ];

    public function getValueAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_numeric($value) && !preg_match('/[^\d.]/', $value)) {
            return $value;
        }

        $decodedValue = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decodedValue;
        }

        return $value;
    }
}
