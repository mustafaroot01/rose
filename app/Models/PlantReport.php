<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantReport extends Model
{
    /** @use HasFactory<\Database\Factories\PlantReportFactory> */
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'reporter_id',
        'citizen_reporter_id',
        'reason',
        'reason_note',
        'image_path',
        'status',
        'handled_by',
        'admin_note',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function citizenReporter(): BelongsTo
    {
        return $this->belongsTo(Citizen::class, 'citizen_reporter_id');
    }

    public function reporterName(): ?string
    {
        return $this->citizenReporter?->name ?? $this->reporter?->name;
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
