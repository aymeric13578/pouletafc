<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'stock_init',
        'price',
        'commission',
        'status',
        'locality',
        'bar_code',
        'description',
        'created_at',
        'updated_at',
        'deleted_at',
        'sale_at',
        'img',
        'slug',
        'product_image1',
        'product_image2',
        'product_image3',
        'product_video1',
        'product_video2',
        'product_length',
        'product_width',
        'product_epaisseur',
        'product_volume',
        'product_color',
        'product_weigth',
        'parameter1',
        'parameter2',
        'ref',
        'designation_tech',
        'id_category',
        'id_sub_category',
        'id_shop',
        'id_merchand',
    ];

    // protected $dates = ['deleted_at'];

    // Relations
    public function category()
    {
        return $this->belongsTo(Category::class, 'id_category');
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'id_sub_category');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'id_shop');
    }

    public function merchand()
    {
        return $this->belongsTo(Merchand::class, 'id_merchand');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    // Fonction pour mettre à jour le produit
    public function updateService(array $data)
    {
        return tap($this)->update($data);
    }
}
