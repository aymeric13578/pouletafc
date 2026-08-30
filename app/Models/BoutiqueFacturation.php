<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ce que l'entreprise perçoit sur une boutique : commission ou abonnement.
 *
 * En mode commission, le prix affiché au client est majoré du taux et cette
 * majoration revient à l'entreprise ; le marchand ne voit jamais que son prix
 * de base. En mode abonnement, rien n'est majoré : la boutique doit un montant
 * qui lui est propre, et son espace marchand l'avertit à l'approche de
 * l'échéance.
 */
class BoutiqueFacturation extends Model
{
    use HasFactory;

    protected $table = 'boutique_facturations';

    public const MODE_COMMISSION = 'commission';
    public const MODE_ABONNEMENT = 'abonnement';

    public const PORTEE_BOUTIQUE = 'boutique';
    public const PORTEE_PRODUITS = 'produits';

    /** Périodicités d'abonnement, et le nombre de mois correspondant. */
    public const PERIODICITES = [
        'mensuel' => 1,
        'trimestriel' => 3,
        'annuel' => 12,
    ];

    /** Nombre de jours avant l'échéance à partir duquel le marchand est averti. */
    public const PREAVIS_JOURS = 3;

    protected $fillable = [
        'shop_id',
        'mode',
        'portee',
        'taux',
        'abonnement_montant',
        'abonnement_periodicite',
        'abonnement_echeance',
        'actif',
    ];

    protected $casts = [
        'taux' => 'float',
        'abonnement_montant' => 'integer',
        'abonnement_echeance' => 'date',
        'actif' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function produits(): HasMany
    {
        return $this->hasMany(BoutiqueCommissionProduit::class, 'boutique_facturation_id');
    }

    public function estCommission(): bool
    {
        return $this->actif && $this->mode === self::MODE_COMMISSION;
    }

    public function estAbonnement(): bool
    {
        return $this->actif && $this->mode === self::MODE_ABONNEMENT;
    }

    /**
     * Taux applicable à un produit donné, ou null si rien n'est majoré.
     *
     * En portée « produits », seuls les produits explicitement listés sont
     * majorés : une boutique peut vouloir majorer ses plats sans toucher à ses
     * boissons. Chacun peut porter son propre taux, à défaut celui de la
     * boutique.
     */
    public function tauxPour(int $produitId): ?float
    {
        if (! $this->estCommission()) {
            return null;
        }

        if ($this->portee === self::PORTEE_BOUTIQUE) {
            return $this->taux > 0 ? $this->taux : null;
        }

        $ligne = $this->produits->firstWhere('product_id', $produitId);

        if (! $ligne) {
            return null;
        }

        $taux = $ligne->taux ?? $this->taux;

        return $taux > 0 ? (float) $taux : null;
    }

    /**
     * Jours restants avant l'échéance de l'abonnement — négatif si dépassée.
     * Null hors mode abonnement ou sans échéance fixée.
     */
    public function joursAvantEcheance(): ?int
    {
        if (! $this->estAbonnement() || ! $this->abonnement_echeance) {
            return null;
        }

        return (int) now()->setTimezone('Africa/Douala')
            ->startOfDay()
            ->diffInDays($this->abonnement_echeance->copy()->startOfDay(), false);
    }

    /** Le marchand doit-il être averti maintenant ? */
    public function doitAvertir(): bool
    {
        $jours = $this->joursAvantEcheance();

        return $jours !== null && $jours <= self::PREAVIS_JOURS;
    }

    /** Message affiché dans l'espace marchand, ou null s'il n'y a rien à dire. */
    public function messageEcheance(): ?string
    {
        $jours = $this->joursAvantEcheance();

        if ($jours === null || $jours > self::PREAVIS_JOURS) {
            return null;
        }

        $montant = number_format((int) $this->abonnement_montant, 0, ',', ' ');

        if ($jours < 0) {
            return "Votre abonnement de {$montant} F CFA est échu depuis "
                . abs($jours) . ' jour' . (abs($jours) > 1 ? 's' : '') . '.';
        }

        if ($jours === 0) {
            return "Votre abonnement de {$montant} F CFA est à régler aujourd'hui.";
        }

        return "Il reste {$jours} jour" . ($jours > 1 ? 's' : '')
            . " pour le paiement de votre abonnement de {$montant} F CFA.";
    }

    public static function regles(): array
    {
        return [
            'shop_id' => 'required|integer|exists:shops,id',
            'mode' => 'required|in:commission,abonnement',
            'portee' => 'required|in:boutique,produits',
            /*
             | Le taux n'est exigé qu'en mode commission : un abonnement ne
             | majore rien, réclamer un pourcentage y serait un champ mort à
             | remplir. Au-delà de 100 %, le client paierait plus du double du
             | prix marchand sans que rien ne le signale.
             */
            'taux' => 'nullable|numeric|min:0|max:100|required_if:mode,commission',
            'abonnement_montant' => 'nullable|integer|min:0|required_if:mode,abonnement',
            'abonnement_periodicite' => 'nullable|in:mensuel,trimestriel,annuel|required_if:mode,abonnement',
            'abonnement_echeance' => 'nullable|date|required_if:mode,abonnement',
        ];
    }
}
