<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Cosmetic extends Model
{
	protected $fillable = [
		'cosmetic_id',
		'type_id',
		'type_name',
		'name',
		'description',
		'rarity_id',
		'rarity_name',
		'series',
		'price',
		'added_date',
		'added_version',
		'copyrighted_audio',
		'upcoming',
		'reactive',
		'release_date',
		'last_appearance',
		'interest',
		'path',
		'gameplay_tags',
		'api_tags',
		'battlepass',
		'set',
	];

    protected $casts = [
        'price' => 'integer',
        'added_date' => 'date',
        'release_date' => 'date',
        'last_appearance' => 'date',
        'copyrighted_audio' => 'boolean',
        'upcoming' => 'boolean',
        'reactive' => 'boolean',
        'interest' => 'float',
        'gameplay_tags' => 'array',
        'api_tags' => 'array',
        'battlepass' => 'array',
        'set' => 'array',
    ];

    protected $appends = [
        'is_new',
        'is_on_sale',
        'is_promoted',
    ];

	public function images()
	{
		return $this->hasMany(CosmeticImage::class);
	}

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_cosmetics')
            ->withPivot('purchase_price', 'purchased_at')
            ->withTimestamps();
    }

    public function getIsNewAttribute(): bool
    {
        if (!$this->added_date) {
            return false;
        }

        return Carbon::parse($this->added_date)->greaterThanOrEqualTo(now()->subDays(30));
    }

    public function getIsOnSaleAttribute(): bool
    {
        if (is_null($this->interest)) {
            return false;
        }

        return $this->interest >= 0.6 && $this->interest < 0.75;
    }
    
    public function getIsPromotedAttribute(): bool
    {
        if (is_null($this->interest)) {
            return false;
        }

        return $this->interest >= 0.75;
    }
}
