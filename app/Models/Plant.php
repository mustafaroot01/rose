<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plant extends Model
{
    /** @use HasFactory<\Database\Factories\PlantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'citizen_id',
        'campaign_id',
        'category_id',
        'type',
        'name',
        'planting_date',
        'description',
        'latitude',
        'longitude',
        'governorate_id',
        'district_id',
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'planting_date' => 'date',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PlantImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** رابط كامل لصورة النبتة الرئيسية، أو null. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? url('api/media/'.$this->image_path) : null;
    }

    /**
     * كل صور النبتة كروابط كاملة — الصورة الرئيسية أولًا ثم صور المعرض.
     * تُستخدم لعرض الشرائح في التطبيق.
     */
    public function imageUrls(): array
    {
        $urls = [];

        if ($this->image_path) {
            $urls[] = url('api/media/'.$this->image_path);
        }

        foreach ($this->images as $img) {
            $urls[] = $img->url();
        }

        return $urls;
    }

    /** عمر النبتة بصيغة عربية مقروءة من تاريخ الزرع. */
    public function ageLabel(): ?string
    {
        if (! $this->planting_date) {
            return null;
        }

        $days = (int) $this->planting_date->diffInDays(now());
        $years = intdiv($days, 365);
        $months = intdiv($days % 365, 30);

        if ($years > 0) {
            return $years.' سنة'.($months ? " و{$months} شهر" : '');
        }

        if ($months > 0) {
            return $months.' شهر';
        }

        if ($days <= 0) {
            return 'زُرعت اليوم';
        }

        if ($days === 1) {
            return 'يوم واحد';
        }

        return "{$days} يوم";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function owner(): User|Citizen|null
    {
        return $this->citizen ?? $this->user;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PlantCategory::class, 'category_id');
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PlantReport::class);
    }

    public function waterings(): HasMany
    {
        return $this->hasMany(PlantWatering::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PlantLike::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PlantActivity::class)->latest();
    }

    /**
     * تسجيل حدث في هستوري النبتة.
     */
    public function logActivity(string $type, ?string $description = null, ?int $userId = null, ?array $meta = null): PlantActivity
    {
        return $this->activities()->create([
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
