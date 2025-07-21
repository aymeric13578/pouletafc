<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'ref',
        'slug',
        'subcategory_count',
        'product_count',
        'image'
    ];


    public function shop()
    {
        return $this->belongsTo(Shop::class, 'id_shop');
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'id_category');
    }
    public function subCategories()
    {
        return $this->hasMany(SubCategory::class,'id_category');
    }

}
