<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['image_path', 'title', 'link', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** رابط كامل لصورة البنر يمرّ عبر مسار الوسائط (CORS). */
    public function url(): string
    {
        return url('api/media/'.$this->image_path);
    }
}
