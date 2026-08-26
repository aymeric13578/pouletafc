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

    /*
    | "name" ne correspond à aucune colonne — la table porte "shop_name" — tandis
    | que id_user et status, eux bien réels, étaient absents : toute écriture de
    | masse sur ces trois champs était silencieusement ignorée.
    */
    protected $fillable = [
        'shop_name',
        'id_user',
        'status',
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
        'type',
        'opening_hours',
    ];

    protected $casts = [
        'opening_hours' => 'array',
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

    /**
     * Produits portés par la boutique.
     *
     * Nommée "produits" et non "products" : la table possède déjà une colonne
     * product_count, et une relation homonyme entrerait en conflit avec elle
     * lors d'un withCount().
     */
    public function produits()
    {
        return $this->hasMany(Product::class, 'id_shop');
    }
    public function category()
    {
        return $this->hasOne(Category::class, 'id_shop');
    }

    /**
     * La boutique accepte-t-elle une commande en ce moment, d'après ses
     * horaires hebdomadaires ?
     *
     * Sans horaire renseigné, la boutique est considérée toujours ouverte :
     * un marchand qui n'a pas encore rempli ce formulaire ne doit pas se
     * retrouver bloqué de fait. Comparaison en "HH:MM" — ne gère pas un
     * créneau à cheval sur minuit, aucune boutique de cet écosystème n'en a
     * besoin aujourd'hui.
     */
    public function estOuverteMaintenant(): bool
    {
        if (empty($this->opening_hours)) {
            return true;
        }

        $maintenant = now()->setTimezone('Africa/Douala');
        $horaire = $this->opening_hours[(string) $maintenant->dayOfWeekIso] ?? null;

        if (! $horaire || ($horaire['closed'] ?? false)) {
            return false;
        }

        $heure = $maintenant->format('H:i');

        return $heure >= ($horaire['opens_at'] ?? '00:00')
            && $heure <= ($horaire['closes_at'] ?? '23:59');
    }

}
