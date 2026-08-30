<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grille tarifaire d'un service, découpée en plages horaires.
 *
 * Remplace, service par service, les colonnes plates de `parameters`
 * (clando_kilometer, min_price_clando, command_kilometer...) : une grille par
 * service, et 2 à 5 plages horaires par grille qui s'appliquent
 * automatiquement selon l'heure — un tarif de nuit n'a pas de raison d'être
 * celui de midi.
 *
 * Une seule grille s'applique à la fois par service, celle au statut
 * 'Success'. L'activation passe par activer(), qui garantit cette unicité :
 * sans elle, deux grilles actives pour le même service laisseraient MySQL
 * choisir arbitrairement laquelle facturer.
 */
class Tarif extends Model
{
    use HasFactory;

    public const ACTIF = 'Success';
    public const INACTIF = 'pending';

    public const CLANDO = 'clando';
    public const LIVRAISON = 'livraison';
    public const COURSIER = 'coursier';

    /** Les services facturés, et leur nom tel qu'il s'affiche. */
    public const SERVICES = [
        self::CLANDO => 'Clando',
        self::LIVRAISON => 'Livraison',
        self::COURSIER => 'Course coursier',
    ];

    /**
     * Services distinguant une course VIP d'une course classique.
     *
     * Le clando seul : une livraison de boutique et une course de coursier
     * n'ont pas de variante VIP, et afficher ces champs sur leur formulaire
     * ne ferait que suggérer un réglage sans effet.
     */
    public const SERVICES_AVEC_VIP = [self::CLANDO];

    protected $fillable = ['service', 'libelle', 'status'];

    public function plages(): HasMany
    {
        return $this->hasMany(TarifPlage::class)->orderBy('ordre');
    }

    /** La grille appliquée pour ce service, ou null si aucune. */
    public static function actif(string $service): ?self
    {
        return static::where('service', $service)
            ->where('status', self::ACTIF)
            ->with('plages')
            ->first();
    }

    public function estActif(): bool
    {
        return $this->status === self::ACTIF;
    }

    /**
     * La plage qui s'applique à l'instant donné.
     *
     * Retombe sur la première plage quand aucune ne couvre l'heure courante :
     * une grille trouée — 06h-12h et 14h-18h, rien entre les deux — ne doit
     * pas rendre le service infacturable, ce qui se traduirait côté
     * application par une course à 0 F.
     */
    public function plageCourante(?\DateTimeInterface $instant = null): ?TarifPlage
    {
        $plages = $this->plages;

        if ($plages->isEmpty()) {
            return null;
        }

        $heure = ($instant ? \Carbon\Carbon::instance($instant) : now())
            ->setTimezone('Africa/Douala')
            ->format('H:i');

        return $plages->first(fn (TarifPlage $plage) => $plage->couvre($heure))
            ?? $plages->first();
    }

    /**
     * Rend cette grille la seule appliquée pour son service.
     *
     * En transaction : une désactivation qui passerait sans l'activation
     * laisserait le service sans grille, et les prix retomberaient
     * silencieusement sur l'ancienne table `parameters`.
     */
    public function activer(): void
    {
        \DB::transaction(function () {
            static::where('service', $this->service)
                ->where('id', '!=', $this->id)
                ->where('status', self::ACTIF)
                ->update(['status' => self::INACTIF]);

            $this->update(['status' => self::ACTIF]);
        });
    }

    /** La grille appliquée ne se supprime pas : le service n'aurait plus de tarif. */
    public function estSupprimable(): bool
    {
        return ! $this->estActif();
    }
}
