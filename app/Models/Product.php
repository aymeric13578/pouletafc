<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

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
        'delete_at',
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


    //Relations
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'id_category');
    }
    public function subCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SubCategory::class, 'id_category');
    }
    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shop::class, 'id_shop');
    }

    //Functions
    public function updateService(array $data): Model|Builder
    {
        return tap($this)->update($data);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function merchand()
    {
        return $this->belongsTo(Merchand::class,'id_merchand');
    }
}
