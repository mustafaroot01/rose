<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'subtitle',
        'icon',
        'color',
        'link',
        'is_seen',
    ];

    protected function casts(): array
    {
        return [
            'is_seen' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function send(int $userId, string $type, string $title, ?string $subtitle = null, ?string $icon = null, string $color = 'primary', ?string $link = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'icon' => $icon,
            'color' => $color,
            'link' => $link,
        ]);
    }

    public static function broadcast(string $type, string $title, ?string $subtitle = null, ?string $icon = null, string $color = 'primary', ?string $link = null): void
    {
        $admins = User::role(['admin', 'supervisor'])->pluck('id');

        $records = $admins->map(fn (int $id) => [
            'user_id' => $id,
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'icon' => $icon,
            'color' => $color,
            'link' => $link,
            'is_seen' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        self::insert($records);
    }
}
