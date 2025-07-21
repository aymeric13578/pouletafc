<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'sellers';

    protected $fillable = [
        'seller_full_name',
        'seller_code',
        'seller_telephone1',
        'seller_telephone2',
        'seller_telephone3',
        'seller_address',
        'seller_email1',
        'seller_email2',
        'seller_niu',
        'product_count',
        'seller_photo',
        'slug',

        'shop_id',
    ];

    // Relations

    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shop::class, "shop_id");
    }
}
