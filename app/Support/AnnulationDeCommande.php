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
     * Tant que rien n'est livré. On ne l'arrête pas au moment où un agent prend
     * la course : c'est souvent là, en voyant le livreur partir, qu'un client
     * se rend compte qu'il s'est trompé d'adresse ou qu'il ne sera pas chez lui.
     * Lui refuser l'annulation à cet instant ne fait pas disparaître le
     * problème, cela oblige seulement à téléphoner au comptoir.
     */
    public static function annulableParLeClient(?Model $ligne): bool
    {
        return $ligne !== null
            && ! in_array($ligne->status, self::STATUTS_CLOS, true);
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
