<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['house_id', 'user_id', 'message', 'ratings']; // Ensure these are your actual columns

    protected $with = ['user'];

    public function house()
    {
        return $this->belongsTo(House::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->select(['id', 'name', 'profile_image']);
    }

}