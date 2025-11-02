<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CosmeticImage extends Model
{
    protected $fillable = [
        'cosmetic_id',
        'type',
        'url',
    ];

    public function cosmetic()
    {
        return $this->belongsTo(Cosmetic::class);
    }
}
