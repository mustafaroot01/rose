<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Citizen extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'avatar',
        'status',
        'account_type_id',
        'governorate_id',
        'district_id',
        'nahiya_id',
        'phone_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** رابط كامل لصورة المواطن، أو null إن لم توجد. */
    public function avatarUrl(): ?string
    {
        return $this->avatar ? url('api/media/'.$this->avatar) : null;
    }

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_participants')
            ->withTimestamps()
            ->withPivot('joined_at');
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function nahiya(): BelongsTo
    {
        return $this->belongsTo(Nahiya::class);
    }
}
