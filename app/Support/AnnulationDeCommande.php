<?php

namespace App\Support;

use App\Models\Clando;
use App\Models\order_detail;
use Illuminate\Database\Eloquent\Model;

/**
 * Annuler une commande, une livraison ou une course — et dire pourquoi.
 *
 * Le motif n'est pas une formalité administrative. Une commande annulée parce
 * que le produit manquait, parce que le client s'est ravisé, parce que l'adresse
 * était introuvable ou parce qu'aucun agent n'a répondu, ce sont quatre
 * problèmes distincts : sans le motif, ils se confondent dans un même compteur
 * d'échecs dont on ne peut rien tirer.
 *
 * Les trois écrans qui annulent — le mur des commandes, la carte des clandos et
 * l'application agent — passent tous par ici, faute de quoi chacun poserait sa
 * propre idée de ce qu'est une annulation.
 */
class AnnulationDeCommande
{
    /**
     * Le statut d'une ligne annulée.
     *
     * La colonne est un enum en production : « failed » en fait partie, un
     * « cancelled » qui semblerait plus juste ferait échouer l'écriture.
     */
    public const STATUT = 'failed';

    /** Longueur minimale d'un motif : en deçà, ce n'est pas une explication. */
    public const MOTIF_MINIMUM = 3;

    public const MOTIF_MAXIMUM = 255;

    /**
     * Motifs proposés d'un geste, pour que l'annulation reste rapide au comptoir.
     *
     * La liste n'est pas fermée : le champ libre reste ouvert, parce qu'aucune
     * liste ne prévoit tout et qu'une case « Autre » sans texte ne renseigne
     * rien.
     */
    public const MOTIFS_COURANTS = [
        'Le client s\'est rétracté',
        'Client injoignable',
        'Adresse introuvable',
        'Produit indisponible',
        'Aucun agent disponible',
        'Erreur de saisie',
        'Paiement non honoré',
    ];

    /**
     * Motifs proposés au client lui-même, à la première personne.
     *
     * Distincts de MOTIFS_COURANTS : ceux-là sont écrits pour le comptoir ou
     * l'agent qui annule *pour* quelqu'un d'autre (« Client injoignable »,
     * « Adresse introuvable »...). Un client qui annule sa propre commande ne
     * parle pas de lui à la troisième personne.
     */
    public const MOTIFS_CLIENT = [
        'J\'ai changé d\'avis',
        'J\'ai trouvé une autre solution',
        'Le temps d\'attente est trop long',
        'Je me suis trompé d\'adresse',
        'Le prix ne me convient plus',
        'Autre raison',
    ];

    /**
     * Le motif est-il exploitable ?
     *
     * Refuser le vide n'est pas de la rigidité : une annulation sans motif est
     * exactement ce qu'on cherche à ne plus avoir.
     */
    public static function motifValide(?string $motif): bool
    {
        $motif = trim((string) $motif);

        return mb_strlen($motif) >= self::MOTIF_MINIMUM
            && mb_strlen($motif) <= self::MOTIF_MAXIMUM;
    }

    public static function nettoyerLeMotif(?string $motif): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $motif)), 0, self::MOTIF_MAXIMUM);
    }

    /**
     * Applique l'annulation à une commande ou à une course.
     *
     * @param  string  $auteur  « client », « agent » ou « admin »
     */
    public static function appliquer(Model $ligne, string $motif, string $auteur): bool
    {
        if (! self::motifValide($motif)) {
            return false;
        }

        $ligne->status = self::STATUT;

        /*
        | Les colonnes sont ajoutées par migration, mais la production porte des
        | tables créées à la main : on n'écrit que ce qui existe réellement,
        | plutôt que de faire échouer l'annulation elle-même. Une commande
        | annulée sans motif enregistré vaut mieux qu'une commande qu'on ne peut
        | pas annuler.
        */
        foreach ([
            'cancel_reason' => self::nettoyerLeMotif($motif),
            'cancelled_at' => now(),
            'cancelled_by' => $auteur,
        ] as $colonne => $valeur) {
            if (ColonnesDisponibles::existe($ligne->getTable(), $colonne)) {
                $ligne->{$colonne} = $valeur;
            }
        }

        return $ligne->save();
    }

    /**
     * Une commande annulée n'est plus à prendre.
     *
     * Sert au refus côté serveur : un agent dont la fenêtre était déjà ouverte
     * au moment de l'annulation appuie sur « Prendre » sans rien savoir, et
     * partait jusqu'ici livrer une commande qui n'existe plus.
     */
    public static function estAnnulee(?Model $ligne): bool
    {
        return $ligne !== null && $ligne->status === self::STATUT;
    }

    /**
     * La ligne est-elle encore prenable par un agent ?
     *
     * « want » est le statut posé par le bouton « Colis prêt » du comptoir :
     * c'est le seul moment où une commande attend qu'on la prenne.
     */
    public static function encorePrenable(?Model $ligne): bool
    {
        return $ligne !== null
            && $ligne->status === 'want'
            && $ligne->id_agent === null;
    }

    /**
     * Statuts sur lesquels il n'y a plus rien à annuler.
     *
     * « Success » est une commande livrée : l'annuler après coup ne défait pas
     * la livraison, cela ne ferait que fausser les comptes. « failed » et
     * « declin » sont déjà des fins de parcours.
     */
    private const STATUTS_CLOS = ['Success', self::STATUT, 'declin'];

    /**
     * Le client peut-il encore annuler lui-même ?
     *
     * Mince enveloppe booléenne autour de motifDeBlocageClient() : gardée pour
     * les appelants qui n'ont besoin que d'un oui/non (takeClandoCommand et
     * consorts vérifient encorePrenable/estAnnulee, pas celle-ci — seuls
     * declinCommand et AnnulationController::annuler s'en servent, tous deux
     * dans un contexte où le motif précis n'est pas nécessaire).
     */
    public static function annulableParLeClient(?Model $ligne, string $type = 'clando'): bool
    {
        return self::motifDeBlocageClient($ligne, $type) === null;
    }

    /**
     * Pourquoi le client ne peut plus annuler lui-même, ou null s'il le peut.
     *
     * Décision unique, utilisée à la fois par la vérification d'éligibilité
     * (avant d'afficher un formulaire d'annulation) et par l'annulation
     * elle-même (avant de l'appliquer) : les deux doivent toujours être
     * d'accord, sinon un client verrait « vous pouvez annuler » puis se
     * ferait refuser au moment de confirmer.
     *
     * Trois obstacles, du plus tardif au plus précoce :
     *  - la commande est déjà à son terme (livrée, déjà annulée, déjà déclinée) ;
     *  - la course a déjà commencé (l'agent a récupéré le client/le colis,
     *    statut « take ») — annuler ne défait pas un trajet en cours ;
     *  - pour un clando seulement, l'agent est arrivé au point de prise en
     *    charge sans avoir encore récupéré le client : rien ne le signale par
     *    un statut dédié, seule la proximité entre sa position et celle du
     *    client permet de le déduire.
     */
    public static function motifDeBlocageClient(?Model $ligne, string $type = 'clando'): ?string
    {
        if ($ligne === null) {
            return 'INTROUVABLE';
        }

        if (in_array($ligne->status, self::STATUTS_CLOS, true)) {
            return 'ALREADY_CLOSED';
        }

        if ($ligne->status === 'take') {
            return 'COURSE_STARTED';
        }

        if ($type === 'clando' && $ligne->status === 'process' && $ligne instanceof Clando
            && self::agentEstArriveAuPointDePriseEnCharge($ligne)) {
            return 'DRIVER_ARRIVED';
        }

        return null;
    }

    /**
     * L'agent est-il tout près du point de prise en charge du client ?
     *
     * Ni le clando ni son statut ne portent de case « arrivé » : le seul
     * signal disponible est la distance entre la position de l'agent
     * (latAgent/lonAgent, rafraîchie en continu par l'app agent) et celle du
     * client au moment de sa demande (latMyPosition/lonMyPosition). En
     * dessous du rayon, on considère l'agent arrivé.
     */
    private static function agentEstArriveAuPointDePriseEnCharge(Clando $clando, float $rayonMetres = 150.0): bool
    {
        if ($clando->latAgent === null || $clando->lonAgent === null
            || $clando->latMyPosition === null || $clando->lonMyPosition === null) {
            return false;
        }

        return Distance::metres(
            (float) $clando->latMyPosition, (float) $clando->lonMyPosition,
            (float) $clando->latAgent, (float) $clando->lonAgent
        ) <= $rayonMetres;
    }

    /**
     * Retrouve une commande ou une course par son type et son identifiant.
     */
    public static function retrouver(string $type, $id): ?Model
    {
        return match ($type) {
            'clando' => Clando::find($id),
            'order' => order_detail::find($id),
            default => null,
        };
    }
}
