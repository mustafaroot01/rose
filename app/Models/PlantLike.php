<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantLike extends Model
{
    protected $fillable = ['plant_id', 'citizen_id'];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }
}
