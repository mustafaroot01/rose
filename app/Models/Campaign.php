<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_path',
        'governorate_id',
        'district_id',
        'goal_trees',
        'starts_at',
        'ends_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'goal_trees' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $c) {
            if (empty($c->slug)) {
                $c->slug = static::uniqueSlug($c->title);
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'campaign';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }

    // ───────────────────────── العلاقات ─────────────────────────

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Citizen::class, 'campaign_participants')
            ->withTimestamps()
            ->withPivot('joined_at');
    }

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ───────────────────────── مساعدات ─────────────────────────

    public function coverUrl(): ?string
    {
        return $this->cover_path ? url('api/media/'.$this->cover_path) : null;
    }

    /** عدد الأشجار المعتمدة ضمن الحملة. */
    public function approvedCount(): int
    {
        return $this->plants()->where('status', 'approved')->count();
    }

    /** نسبة الإنجاز مقابل الهدف (0..100). */
    public function progressPercent(): int
    {
        if ($this->goal_trees <= 0) {
            return 0;
        }

        return min(100, (int) round($this->approvedCount() / $this->goal_trees * 100));
    }
}
