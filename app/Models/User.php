<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'actual_lon_position_agent'
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


}
