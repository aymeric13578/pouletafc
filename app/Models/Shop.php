<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'shops';

    protected $fillable = [
        'name',
        'ref',
        'banner',
        'phone1',
        'phone2',
        'address',
        'email1',
        'city',
        'email2',
        'commercial_register',
        'commercial_register_file',
        'product_count',
        'logo',
        'slug',
        'id_merchand',
        'description',
        'type'
    ];

    public function merchand()
    {
        return $this->belongsTo(Merchand::class, 'id_merchand');
    }

    /**
     * Utilisateur rattaché à la boutique : c'est lui qui la gère depuis l'espace
     * marchand, et c'est ce lien qui l'y redirige à la connexion.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
    public function category()
    {
        return $this->hasOne(Category::class, 'id_shop');
    }

}
