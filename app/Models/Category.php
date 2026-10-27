<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $fillable=[
        'name',
        'image',
        'restaurant_id',
    ];

    public function products(){
        return $this->hasMany(Product::class);
    }
    public function restaurant(){
        return $this->belongsTo(Restaurant::class);
    }
}
