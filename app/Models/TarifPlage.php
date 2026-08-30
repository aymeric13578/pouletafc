<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une tranche horaire d'une grille tarifaire, avec ses propres prix.
 *
 * C'est ici que vit le calcul du prix d'une course : plancher, plafond, prix
 * au kilomètre et commissions. Le plafond n'existait nulle part auparavant —
 * rien ne bornait une course de 40 km.
 */
class TarifPlage extends Model
{
    use HasFactory;

    protected $table = 'tarif_plages';

    protected $fillable = [
        'tarif_id',
        'debut',
        'fin',
        'prix_min',
        'prix_max',
        'prix_km',
        'commission',
        'commission_vip',
        'majoration_vip',
        'ordre',
    ];

    protected $casts = [
        'prix_min' => 'integer',
        'prix_max' => 'integer',
        'prix_km' => 'integer',
        'commission' => 'float',
        'commission_vip' => 'float',
        'majoration_vip' => 'float',
        'ordre' => 'integer',
    ];

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }

    /** "HH:MM", quel que soit le format rendu par MySQL ("08:00:00"). */
    public function debutCourt(): string
    {
        return substr((string) $this->debut, 0, 5);
    }

    public function finCourte(): string
    {
        return substr((string) $this->fin, 0, 5);
    }

    /**
     * Cette plage couvre-t-elle l'heure donnée ("HH:MM") ?
     *
     * Le cas à cheval sur minuit est traité explicitement : une plage de nuit
     * 18:00 → 06:00 a une fin *antérieure* à son début, et une comparaison
     * naïve `heure >= debut && heure <= fin` ne la ferait jamais correspondre
     * — c'est-à-dire précisément la plage qu'on cherche à majorer.
     */
    public function couvre(string $heure): bool
    {
        $debut = $this->debutCourt();
        $fin = $this->finCourte();

        if ($debut === $fin) {
            // Plage de 24 h : elle couvre tout.
            return true;
        }

        if ($debut < $fin) {
            return $heure >= $debut && $heure < $fin;
        }

        return $heure >= $debut || $heure < $fin;
    }

    /**
     * Prix d'une course de `$km` kilomètres sur cette plage.
     *
     * Le plancher s'applique avant le plafond : une grille mal saisie où le
     * minimum dépasse le maximum ne doit pas produire un prix négatif ou nul,
     * elle facture le plafond.
     */
    public function prixPour(float $km, bool $vip = false): int
    {
        $prix = $this->prix_km * max($km, 0);

        if ($vip && $this->majoration_vip) {
            $prix += $prix * $this->majoration_vip / 100;
        }

        $prix = max($prix, (float) $this->prix_min);

        if ($this->prix_max !== null && $this->prix_max > 0) {
            $prix = min($prix, (float) $this->prix_max);
        }

        // Arrondi au multiple de 50 supérieur : aucun prix affiché dans cet
        // écosystème n'a jamais d'unité en dessous de 50 F CFA.
        return (int) (ceil($prix / 50) * 50);
    }

    /** Ce que l'entreprise retient sur une course facturée `$prix`. */
    public function commissionPour(float $prix, bool $vip = false): float
    {
        $taux = $vip && $this->commission_vip !== null
            ? $this->commission_vip
            : $this->commission;

        return round($prix * $taux / 100, 2);
    }

    /** Règles de saisie d'une plage, portées par le modèle et non par l'écran. */
    public static function regles(string $prefixe = ''): array
    {
        return [
            $prefixe . 'debut' => 'required|date_format:H:i',
            $prefixe . 'fin' => 'required|date_format:H:i',
            $prefixe . 'prix_min' => 'required|integer|min:0',
            // Facultatif : sans plafond, seul le prix au kilomètre décide.
            $prefixe . 'prix_max' => 'nullable|integer|min:0',
            $prefixe . 'prix_km' => 'required|integer|min:0',
            // Appliqués tels quels comme pourcentages : au-delà de 100, la
            // commission dépasserait le montant de la course.
            $prefixe . 'commission' => 'required|numeric|min:0|max:100',
            $prefixe . 'commission_vip' => 'nullable|numeric|min:0|max:100',
            $prefixe . 'majoration_vip' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
