<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantWatering extends Model
{
    /** @use HasFactory<\Database\Factories\PlantWateringFactory> */
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'user_id',
        'citizen_id',
        'watered_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'watered_at' => 'datetime',
        ];
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
