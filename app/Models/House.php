<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class House extends Model
{
    use HasFactory;

    protected $guarded = [];
    
    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function facilities()
    {
        return $this->hasMany(Facility::class, 'property_id')->where('type', 'house');
    }

    public function houseViews()
    {
        return $this->hasOne(HouseView::class);
    }

    public function gallery()
    {
        return $this->hasMany(HouseGallery::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}