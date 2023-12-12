<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estate extends Model
{
    use HasFactory;

    public function manager()
    {
        return $this->belongsTo(Manager::class)->select(['id', 'name', 'email', 'phone', 'county', 'profile_image']);
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }
}