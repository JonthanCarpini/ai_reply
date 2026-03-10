<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceApp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_type',
        'app_name',
        'app_url',
        'download_instructions',
        'setup_instructions',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getDeviceTypes(): array
    {
        return [
            'lg_tv' => 'Smart TV LG (webOS)',
            'samsung_tv' => 'Smart TV Samsung (Tizen)',
            'roku_tv' => 'Smart TV Roku',
            'android_tv' => 'Smart TV Android',
            'fire_tv' => 'Amazon Fire TV',
            'apple_tv' => 'Apple TV',
            'tvbox' => 'TV Box Android',
            'android_phone' => 'Celular Android',
            'iphone' => 'iPhone/iPad',
            'windows' => 'Windows PC',
            'mac' => 'Mac',
            'linux' => 'Linux',
            'chromecast' => 'Chromecast',
            'other' => 'Outro',
        ];
    }
}
