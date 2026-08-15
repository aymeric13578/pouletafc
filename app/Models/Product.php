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
        /*
         | Ce produit peut-il être proposé en accompagnement ?
         |
         | Il reste vendable seul : le drapeau ne le retire pas du catalogue, il
         | indique seulement qu'on peut le rattacher à d'autres produits.
         */
        'is_complement',
    ];

    protected $casts = [
        'is_complement' => 'boolean',
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

    /**
     * Compléments proposés avec ce produit.
     *
     * Des produits eux aussi : une portion de frites se vend seule autant
     * qu'elle accompagne un poulet.
     */
    public function complements()
    {
        return $this->belongsToMany(
            self::class,
            'product_complement',
            'product_id',
            'complement_id'
        )->withTimestamps();
    }

    /**
     * Produits qui proposent ce complément.
     *
     * Sert à prévenir avant de retirer un complément du catalogue : sans cela on
     * le supprime sans voir qu'il était rattaché à vingt plats.
     */
    public function proposePar()
    {
        return $this->belongsToMany(
            self::class,
            'product_complement',
            'complement_id',
            'product_id'
        );
    }

    /** Ce produit propose-t-il au moins un complément ? */
    public function aDesComplements(): bool
    {
        return $this->complements()->exists();
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
