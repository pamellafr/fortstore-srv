<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

	public function images()
	{
		return $this->hasMany(CosmeticImage::class);
	}
}
