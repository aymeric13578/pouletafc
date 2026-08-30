<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Grille tarifaire de l'application : prix au kilomètre, courses minimales et
 * pourcentages de commission.
 *
 * Une seule ligne s'applique à la fois — celle au statut 'Success'. Tout le
 * code appelant fait `Parameter::where('status', 'Success')->first()`, si bien
 * qu'en cas d'égalité MySQL choisirait arbitrairement laquelle appliquer.
 * L'activation passe donc par activer(), qui garantit l'unicité.
 */
class Parameter extends Model
{
    use HasFactory;

    public const ACTIF = 'Success';
    public const INACTIF = 'pending';

    protected $fillable = [
        'clando_kilometer',
        'command_kilometer',
        /*
         | Tarif des courses de coursier (envoi de colis entre particuliers).
         | Écrit en dur dans l'application cliente jusqu'ici — 500 F + 200 F/km
         | — donc impossible à ajuster sans republier l'application.
         */
        'coursier_kilometer',
        'min_price_clando',
        'min_price_command',
        'min_price_coursier',
        'clando_agent_commission',
        'clando_agent_command',
        'vip_percentage',
        // Absent de la liste jusqu'ici : la colonne existait en base mais aucun
        // écran ne pouvait l'écrire, la commission des livreurs restait à 0.
        'delivery_agent_commission',
        // Tarif au kilo, appliqué quand le comptoir corrige un panier au poids
        // réel depuis le mur des commandes.
        'price_per_kg',
        /*
         | Barème de notation : ce que vaut chaque appréciation laissée par un
         | client. Auparavant écrit en dur dans NoteController, donc incorrigible
         | sans toucher au code.
         */
        'note_points_verybad',
        'note_points_bad',
        'note_points_average',
        'note_points_good',
        'note_points_excellent',
        /*
         | Fenêtre/crédibilité de la note affichée (moyenne pondérée +
         | stabilisation bayésienne, voir NotationAgent::noteAffichee) et
         | réglages du score de distribution (voir DistributionScore).
         */
        'note_fenetre_recente',
        'note_credibilite_c',
        'distribution_vitesse_kmh',
        'distribution_rayon_km',
        'distribution_delai_vague_s',
        'distribution_taille_vague',
        /*
         | Lieu de repli quand le client ne choisit pas sa zone. Désigné parmi
         | ceux que les agents ont enregistrés, plutôt que laissé à la dernière
         | position connue du compte — périmée et identique pour tous.
         */
        'default_pickup_location_id',
        'status'
    ];

    /**
     * Lieu de retrait par défaut, s'il est encore en base.
     *
     * Sans clé étrangère : la table des lieux est alimentée depuis les
     * téléphones des agents et un lieu peut disparaître. On vérifie donc à la
     * lecture plutôt que d'interdire la suppression.
     */
    public function lieuParDefaut()
    {
        return $this->belongsTo(Location::class, 'default_pickup_location_id');
    }

    /**
     * Les cinq appréciations, de la pire à la meilleure.
     *
     * L'ordre compte : il fixe celui des colonnes de configuration comme celui
     * des émoticônes dans les deux applications.
     */
    public const APPRECIATIONS = ['verybad', 'bad', 'average', 'good', 'excellent'];

    /** Barème historique, appliqué tant qu'aucune grille active n'en porte. */
    public const POINTS_PAR_DEFAUT = [
        'verybad' => -2.0,
        'bad' => -1.0,
        'average' => 1.0,
        'good' => 1.5,
        'excellent' => 2.0,
    ];

    /**
     * Points attribués à chaque appréciation par cette grille.
     *
     * @return array<string, float>
     */
    public function pointsDeNotation(): array
    {
        $points = [];

        foreach (self::APPRECIATIONS as $appreciation) {
            $colonne = 'note_points_' . $appreciation;
            $valeur = $this->getAttribute($colonne);

            // Une grille enregistrée avant l'ajout du barème n'a rien dans ces
            // colonnes : on retombe sur l'ancien barème plutôt que de compter
            // zéro partout, ce qui remettrait tous les agents à plat.
            $points[$appreciation] = $valeur === null
                ? self::POINTS_PAR_DEFAUT[$appreciation]
                : (float) $valeur;
        }

        return $points;
    }

    /**
     * Règles de saisie d'une grille. Portées par le modèle plutôt que par
     * l'écran : ce sont des contraintes sur la donnée, et une page Folio pleine
     * page ne se teste pas composant par composant.
     */
    public static function regles(): array
    {
        return [
            'clando_kilometer' => 'required|integer|min:0',
            'command_kilometer' => 'required|integer|min:0',
            'min_price_clando' => 'required|integer|min:0',
            'min_price_command' => 'required|integer|min:0',
            /*
             | Tarif coursier. Facultatif comme price_per_kg, et pour la même
             | raison : les grilles enregistrées avant ces colonnes n'en
             | portent pas, et l'exiger empêcherait de rouvrir puis
             | d'enregistrer une grille existante. Sans valeur, l'application
             | cliente applique son tarif historique (500 F + 200 F/km).
             */
            'coursier_kilometer' => 'nullable|integer|min:0',
            'min_price_coursier' => 'nullable|integer|min:0',
            // Ces quatre valeurs sont appliquées telles quelles comme
            // pourcentages (prix × valeur / 100) : au-delà de 100 la commission
            // dépasserait le montant de la course.
            'clando_agent_commission' => 'required|integer|min:0|max:100',
            'clando_agent_command' => 'required|integer|min:0|max:100',
            'delivery_agent_commission' => 'required|integer|min:0|max:100',
            'vip_percentage' => 'required|integer|min:0|max:100',
            /*
             | Tarif au kilo. Facultatif, contrairement aux autres : les grilles
             | déjà enregistrées n'en portent pas, et exiger une valeur
             | empêcherait de rouvrir puis d'enregistrer une grille existante.
             | Sans tarif, le mur des commandes n'ouvre simplement pas la
             | correction au poids.
             */
            'price_per_kg' => 'nullable|integer|min:0',
            /*
             | Points par appréciation. Décimaux et signés, contrairement au
             | reste : « bien » valait 1,5 dans l'ancien barème, et une mauvaise
             | prestation doit pouvoir retirer des points. Les bornes évitent
             | seulement les valeurs absurdes.
             */
            'note_points_verybad' => 'nullable|numeric|min:-10|max:10',
            'note_points_bad' => 'nullable|numeric|min:-10|max:10',
            'note_points_average' => 'nullable|numeric|min:-10|max:10',
            'note_points_good' => 'nullable|numeric|min:-10|max:10',
            'note_points_excellent' => 'nullable|numeric|min:-10|max:10',
            /*
             | Fenêtre/crédibilité de la note affichée et réglages du score de
             | distribution. Bornes larges mais pas infinies : au-delà, la
             | valeur n'a plus de sens pratique (une fenêtre de 10000 notes,
             | un rayon de 500km) et trahit une saisie erronée.
             */
            'note_fenetre_recente' => 'nullable|integer|min:10|max:2000',
            'note_credibilite_c' => 'nullable|integer|min:1|max:500',
            'distribution_vitesse_kmh' => 'nullable|numeric|min:1|max:120',
            'distribution_rayon_km' => 'nullable|numeric|min:0.5|max:100',
            'distribution_delai_vague_s' => 'nullable|integer|min:1|max:300',
            'distribution_taille_vague' => 'nullable|integer|min:1|max:50',
            /*
             | Facultatif : sans lieu désigné, on ne force rien et le point reste
             | indéterminé comme aujourd'hui. « exists » empêche de pointer un
             | lieu supprimé entre l'ouverture du formulaire et l'enregistrement.
             */
            'default_pickup_location_id' => 'nullable|integer|exists:locations,id',
        ];
    }

    public static function messagesValidation(): array
    {
        return [
            'required' => 'Cette valeur est obligatoire',
            'integer' => 'Entrez un nombre entier',
            'min' => 'La valeur ne peut pas être négative',
            'max' => 'Un pourcentage ne peut pas dépasser 100',
            'numeric' => 'Entrez un nombre',
            'note_points_verybad.min' => 'Un barème va de -10 à 10',
            'note_points_bad.min' => 'Un barème va de -10 à 10',
            'note_points_average.min' => 'Un barème va de -10 à 10',
            'note_points_good.min' => 'Un barème va de -10 à 10',
            'note_points_excellent.min' => 'Un barème va de -10 à 10',
        ];
    }

    /** La configuration effectivement appliquée, ou null si aucune n'est active. */
    public static function active(): ?self
    {
        return static::where('status', self::ACTIF)->first();
    }

    public function estActive(): bool
    {
        return $this->status === self::ACTIF;
    }

    /**
     * La grille appliquée ne se supprime pas : sans elle, les commissions
     * calculées valent 0 et l'application mobile ne reçoit plus aucun tarif.
     */
    public function estSupprimable(): bool
    {
        return ! $this->estActive();
    }

    /**
     * Rend cette configuration la seule active.
     *
     * En transaction : une désactivation qui passerait sans l'activation
     * laisserait l'application sans grille tarifaire, et les commissions
     * retomberaient silencieusement à 0.
     */
    public function activer(): void
    {
        \DB::transaction(function () {
            static::where('id', '!=', $this->id)
                ->where('status', self::ACTIF)
                ->update(['status' => self::INACTIF]);

            $this->update(['status' => self::ACTIF]);
        });
    }
}