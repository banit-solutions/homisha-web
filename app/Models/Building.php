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

    public function scopeNearby($query, $latitude, $longitude, $distance)
    {
        $haversine = "(6371 * acos(cos(radians($latitude)) 
                   * cos(radians(buildings.latitude)) 
                   * cos(radians(buildings.longitude) 
                   - radians($longitude)) 
                   + sin(radians($latitude)) 
                   * sin(radians(buildings.latitude))))";

        return $query->select('*')
            ->selectRaw("{$haversine} as distance")
            ->havingRaw("distance < ?", [$distance]);
    }

}