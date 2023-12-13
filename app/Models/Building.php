<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;
    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function houses()
    {
        return $this->hasMany(House::class);
    }

    public function scopeNearby($query, $latitude, $longitude, $radius = 5)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $haversine = "(
        $earthRadius * acos(
            cos(radians($latitude))
            * cos(radians(latitude))
            * cos(radians(longitude) - radians($longitude))
            + sin(radians($latitude))
            * sin(radians(latitude))
        )
    )";

        return $query->select('buildings.*')
            ->selectRaw("{$haversine} AS distance")
            ->whereRaw("{$haversine} < ?", [$radius])
            ->orderBy('distance');
    }
}