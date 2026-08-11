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
        'min_price_clando',
        'min_price_command',
        'clando_agent_commission',
        'clando_agent_command',
        'vip_percentage',
        // Absent de la liste jusqu'ici : la colonne existait en base mais aucun
        // écran ne pouvait l'écrire, la commission des livreurs restait à 0.
        'delivery_agent_commission',
        // Tarif au kilo, appliqué quand le comptoir corrige un panier au poids
        // réel depuis le mur des commandes.
        'price_per_kg',
        'status'
    ];

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
        ];
    }

    public static function messagesValidation(): array
    {
        return [
            'required' => 'Cette valeur est obligatoire',
            'integer' => 'Entrez un nombre entier',
            'min' => 'La valeur ne peut pas être négative',
            'max' => 'Un pourcentage ne peut pas dépasser 100',
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