<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'category_id',
        'restaurant_id',
        'is_available',
        'stock',
    ];
    use HasFactory;
    public function restaurant()
{
    return $this->belongsTo(Restaurant::class);
}
public function category(){
    return $this->belongsTo(Category::class);
}
}
