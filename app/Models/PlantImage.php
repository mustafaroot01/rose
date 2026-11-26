<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantImage extends Model
{
    protected $fillable = ['plant_id', 'path', 'sort_order'];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /** رابط كامل للصورة يمرّ عبر مسار الوسائط (CORS). */
    public function url(): string
    {
        return url('api/media/'.$this->path);
    }
}
