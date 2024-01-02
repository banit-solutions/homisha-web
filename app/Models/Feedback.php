<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'feedback', 'category'];

    // Inside the Feedback model
    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}