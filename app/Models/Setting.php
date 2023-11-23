<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'v2_settings';
    protected $guarded  = [];

    public function getValueAttribute($value)
    {
        if ($value === null) {
            // 如果值为 null，你可能想要处理这种情况，例如返回一个默认值
            return null; // 或者返回你期望的默认值
        }
        if (!is_string($value)) {
            return $value;
        }
        
        $decodedValue = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decodedValue;
        }

        // 如果不是有效的 JSON 数据，则保持为字符串
        return $value;
    }
}
