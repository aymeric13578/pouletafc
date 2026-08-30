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

    /**
     * Description humaine du prochain horaire d'ouverture, utilisée dans le
     * message d'erreur affiché au client quand la boutique est fermée à la
     * validation du panier (voir PanierValideController). Ne doit être
     * appelée que si estOuverteMaintenant() est déjà false — une boutique
     * sans horaire renseigné est toujours ouverte (voir ci-dessus) et n'a
     * donc pas de "prochaine ouverture" à annoncer.
     */
    public function prochaineOuverture(): ?string
    {
        if (empty($this->opening_hours)) {
            return null;
        }

        $maintenant = now()->setTimezone('Africa/Douala');
        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

        // Encore aujourd'hui, si l'heure d'ouverture n'est pas encore passée.
        $horaireAujourdhui = $this->opening_hours[(string) $maintenant->dayOfWeekIso] ?? null;
        if ($horaireAujourdhui && ! ($horaireAujourdhui['closed'] ?? false)) {
            $ouverture = self::heureValide($horaireAujourdhui['opens_at'] ?? null);
            if ($ouverture && $maintenant->format('H:i') < $ouverture) {
                return "aujourd'hui à {$ouverture}";
            }
        }

        // Sinon le prochain jour ouvert, jusqu'à 7 jours plus tard.
        for ($i = 1; $i <= 7; $i++) {
            $jour = $maintenant->copy()->addDays($i);
            $horaire = $this->opening_hours[(string) $jour->dayOfWeekIso] ?? null;
            $ouverture = self::heureValide($horaire['opens_at'] ?? null);
            if ($horaire && ! ($horaire['closed'] ?? false) && $ouverture) {
                $label = $i === 1 ? 'demain' : $jours[$jour->dayOfWeekIso - 1];
                return "{$label} à {$ouverture}";
            }
        }

        return null;
    }

    /**
     * L'heure telle qu'elle peut être réaffichée, ou null.
     *
     * opening_hours n'est validé qu'en tant que JSON (voir
     * MaBoutiqueController::updateMyShop) : sa structure interne est du texte
     * marchand non contrôlé. Ce message-ci partant vers un autre utilisateur
     * — le client qui valide son panier —, on n'en ressort que ce qui a bien
     * la forme d'une heure, plutôt que de recopier la chaîne telle quelle.
     */
    private static function heureValide($valeur): ?string
    {
        if (! is_string($valeur) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valeur)) {
            return null;
        }

        return $valeur;
    }

}
