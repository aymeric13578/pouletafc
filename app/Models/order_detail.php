<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order_detail extends Model
{
    use HasFactory;

    protected $table = 'order_details';

    protected $fillable = [
        'id_order',
        'product_name',
        'id_customer',
        'price',
        'qty',
        'subtotal',
        'discount',
        'tax',
        'total_ttc',
        'email_customer',
        'phone_customer',
        'date',
        'status',
        'id_cart',
        'longitude',
        'latitude',
        'id_user',
        'ref',
        'payment_method',
        'commission_seller',
        'id_agent',
        'delivery_type',
        'latAgent',
        'lonAgent',
        'matricule_vehicule',
        'address',
        'latShop',
        'lonShop',
        'shop_name',
        'delivery_code',
        'commission_agent',
        'status_paiement',
        'delivery_fees',
        'panier_price',
        // Poids retenu par le comptoir quand il corrige le panier au poids réel.
        'poids_kg',
        // Champs d'une course coursier : point de départ, photo du colis et note
        // libre rassemblant expéditeur, destinataire et instructions de remise.
        'depart',
        'image',
        'note',
        'reception_mode',
        'phone_customer',
        'email_customer',
        'shop_id',
        'date',
        /*
        | Clé de la tentative de commande, et motif d'annulation.
        |
        | Absentes de cette liste, elles étaient silencieusement écartées à la
        | création : la commande s'enregistrait sans sa clé, et un second envoi
        | de la même tentative repartait donc pour une nouvelle commande. Le
        | garde-fou existait, il n'avait simplement rien à comparer.
        */
        'cle_unique',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
        'agent_arrived_at',
    ];

    public function carts()
    {
        return $this->belongsTo(Cart::class, 'id_cart');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'id_agent', 'id_user');
    }

    /**
     * Course de coursier rattachée à la commande.
     *
     * Un coursier n'est pas une entité distincte : c'est un enregistrement de la
     * table clando créé à partir d'une commande (createclandoorder), reconnaissable
     * à son delivery_type « delivery » et à son id_order renseigné. Un clando sans
     * id_order est un simple trajet, sans colis.
     */
    public function coursier()
    {
        return $this->hasOne(Clando::class, 'id_order')
            ->where('delivery_type', 'delivery');
    }
}