<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Remise qu'un marchand a définie sur un de ses produits, depuis l'espace
 * « Ma boutique » de l'application — voir MaBoutiqueController.
 */
class Promotion extends Model
{
    use HasFactory;

    protected $table = 'promotions';

    protected $fillable = [
        'id_shop',
        'id_product',
        'title',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'discount_value' => 'float',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'id_shop');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    /** Validée par l'équipe (status 'Success') et dans sa fenêtre de dates. */
    public function estActive(): bool
    {
        return $this->status === 'Success'
            && now()->between($this->starts_at, $this->ends_at);
    }

    /** Prix du produit une fois cette remise appliquée. */
    public function prixApres(float $prixInitial): float
    {
        if ($this->discount_type === 'percentage') {
            return max(0, $prixInitial * (1 - $this->discount_value / 100));
        }

        return max(0, $prixInitial - $this->discount_value);
    }
}
