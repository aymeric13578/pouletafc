<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'country',
        'birth',
        'phone',
        'email',
        'password',
        'city',
        'role',
        'country',
        'country_code',
        'ref',
        'confirmation_code',
        'status',
        'recoveryPass_code',
        'sexe',
        'id_father',
        'id_country',
        'photo',
        'whatsapp',
        'longitude',
        'latitude',
        'in_activity',
        'actual_lat_position_agent',
        'actual_lon_position_agent',
        // Horodatage du dernier relevé : les cartes s'en servent pour ne pas
        // présenter un point vieux de plusieurs semaines comme du direct.
        'position_updated_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        /*
         | Sans cette conversion, position_updated_at revient sous forme de
         | chaîne et les cartes tombent en 500 en lui demandant une comparaison
         | de dates. Le défaut ne se voit qu'à l'exécution : rien ne le signale
         | à l'écriture.
         */
        'position_updated_at' => 'datetime',
    ];
    public function country()
    {
        return $this->belongsTo(Country::class ,'id_country');
    }

  public function Clando()
    {
        return $this->hasMany(Clando::class,'id_user');
    }
    // Relation avec les éléments du panier
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    // Relation avec les paniers de l'utilisateur
    public function carts()
    {
        return $this->hasMany(Cart::class, 'id_cart');
    }

    public function delivery_address()
    {
        return $this->hasMany(User::class, 'id_user');
    }
    public function agent()
    {
        return $this->hasOne(Agent::class, 'id_user');
    }
    public function merchand()
    {
        return $this->hasOne(Merchand::class, 'id_user');
    }
    public function orderDetail()
{
    return $this->hasMany(order_detail::class,'id_user');
}

    /**
     * Supprime définitivement le compte de l'utilisateur ainsi que toutes les
     * données personnelles associées (conformité Google Play — suppression de compte).
     *
     * Les paniers (carts) et leurs éléments (cart_items) sont supprimés
     * automatiquement par la contrainte onDelete('cascade'). Les autres tables
     * enfant sont purgées explicitement ci-dessous, avec garde de schéma pour
     * rester tolérant si une table/colonne n'existe pas sur un environnement.
     */
    public function purgeAccount(): void
    {
        DB::transaction(function () {
            $childTables = [
                'begin_agent_days'   => 'id_user',
                'order_details'      => 'id_user',
                'delivery_addresses' => 'id_user',
                'clando'             => 'id_user',
            ];

            foreach ($childTables as $table => $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, $this->id)->delete();
                }
            }

            $this->delete();
        });
    }

}
