<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Produit majoré individuellement, quand la commission d'une boutique porte
 * sur une sélection plutôt que sur son catalogue entier.
 *
 * `taux` nullable : sans valeur propre, le produit reprend le taux de la
 * boutique. C'est le cas courant — la colonne n'existe que pour l'exception.
 */
class BoutiqueCommissionProduit extends Model
{
    use HasFactory;

    protected $table = 'boutique_commission_produits';

    protected $fillable = ['boutique_facturation_id', 'product_id', 'taux'];

    protected $casts = ['taux' => 'float'];

    public function facturation(): BelongsTo
    {
        return $this->belongsTo(BoutiqueFacturation::class, 'boutique_facturation_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
