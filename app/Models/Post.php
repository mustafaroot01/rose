<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** منشور في المدونة. */
class Post extends Model
{
    protected $fillable = [
        'title', 'body', 'image_path', 'link_url', 'link_label',
        'is_published', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** المنشورات الظاهرة للتطبيق: منشورة وتاريخ نشرها حلّ. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_published', true)
            ->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? url('api/media/'.$this->image_path) : null;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image' => $this->imageUrl(),
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
            'is_published' => $this->is_published,
            'published_at' => ($this->published_at ?? $this->created_at)?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
