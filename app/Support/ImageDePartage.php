<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Fabrique l'image d'aperçu affichée quand un lien est partagé.
 *
 * WhatsApp, Facebook et Telegram ne montrent pas la photo d'origine : ils la
 * retéléchargent, et renoncent au-delà d'un certain poids — l'aperçu s'affiche
 * alors sans image, ce qui vide le partage de son intérêt. Les photos venant
 * d'un téléphone pèsent couramment plusieurs mégaoctets ; celles du catalogue
 * tiennent aujourd'hui sous les 500 Ko, mais rien n'empêche la prochaine d'être
 * dix fois plus lourde.
 *
 * On sert donc une version normalisée : 1200 × 630, le format que ces robots
 * attendent pour un grand aperçu, en JPEG compressé. Le résultat est mis en
 * cache et n'est refabriqué que si la photo d'origine change.
 */
class ImageDePartage
{
    /** Format attendu par les robots d'aperçu pour une vignette large. */
    public const LARGEUR = 1200;

    public const HAUTEUR = 630;

    /**
     * Au-delà, WhatsApp renonce à l'aperçu. On vise nettement en dessous : le
     * robot dispose rarement d'une bonne connexion, et un aperçu lent est un
     * aperçu que personne ne voit.
     */
    private const POIDS_VISE = 300_000;

    /*
    | Version du cadrage, incluse dans le nom du fichier mis en cache.
    |
    | La clé ne dépend sinon que de la photo d'origine : changer la façon de
    | composer l'aperçu laisserait en place tous ceux déjà fabriqués. Ce numéro
    | est à incrémenter chaque fois que le rendu change.
    |
    | 2 : le cadre est rempli par la photo, au lieu de la poser sur fond blanc.
    */
    private const VERSION = 2;

    /**
     * Chemin du fichier d'aperçu prêt à être servi.
     *
     * @param  string|null  $source  ce qui est enregistré en base : URL absolue ou chemin relatif
     * @param  string  $cle  identifiant stable de l'objet partagé, pour nommer le cache
     */
    public function fabriquer(?string $source, string $cle): string
    {
        $origine = $this->fichierSource($source) ?? $this->logo();

        $signature = substr(md5($origine . '|' . filemtime($origine) . '|' . filesize($origine)), 0, 12);
        $signature = 'v' . self::VERSION . '-' . $signature;
        $destination = $this->dossierCache() . '/' . $cle . '-' . $signature . '.jpg';

        if (is_file($destination)) {
            return $destination;
        }

        try {
            $this->composer($origine, $destination, remplir: $origine !== $this->logo());
        } catch (\Throwable $e) {
            Log::warning('Aperçu de partage non fabriqué', [
                'source' => $origine,
                'message' => $e->getMessage(),
            ]);

            // Mieux vaut la photo d'origine qu'aucune image.
            return $origine;
        }

        $this->purger($cle, $destination);

        return $destination;
    }

    /**
     * Remplit un cadre de 1200 × 630 avec la photo.
     *
     * La photo occupe tout le cadre : on prélève dedans la plus grande zone au
     * format voulu, centrée, et on l'agrandit à la taille du cadre. Un premier
     * essai plaçait la photo entière sur fond blanc — les proportions étaient
     * intactes, mais les bandes blanches occupaient la moitié de l'aperçu et la
     * photo y paraissait minuscule.
     *
     * Rien n'est déformé pour autant : c'est la zone prélevée qui a déjà le
     * format du cadre, la photo n'est jamais étirée dans un sens plus que dans
     * l'autre.
     */
    private function composer(string $origine, string $destination, bool $remplir = true): void
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('GD absent : aucune image ne peut être redimensionnée.');
        }

        $photo = $this->ouvrir($origine);

        $largeurSource = imagesx($photo);
        $hauteurSource = imagesy($photo);

        $format = self::LARGEUR / self::HAUTEUR;

        if (! $remplir) {
            // Le logo de repli n'est pas une photo de plat : le rogner le
            // couperait en deux. Il est posé entier, au centre.
            $this->poserAuCentre($photo, $destination, $largeurSource, $hauteurSource);

            return;
        }

        if ($largeurSource / $hauteurSource > $format) {
            // Photo plus large que le cadre : on garde toute la hauteur.
            $hauteurPrise = $hauteurSource;
            $largeurPrise = (int) round($hauteurSource * $format);
        } else {
            // Photo plus haute : on garde toute la largeur.
            $largeurPrise = $largeurSource;
            $hauteurPrise = (int) round($largeurSource / $format);
        }

        // Centré : sur une photo de plat, le sujet est presque toujours au
        // milieu, et c'est la partie qu'il faut préserver.
        $depuisX = (int) (($largeurSource - $largeurPrise) / 2);
        $depuisY = (int) (($hauteurSource - $hauteurPrise) / 2);

        $cadre = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagefill($cadre, 0, 0, imagecolorallocate($cadre, 255, 255, 255));

        imagecopyresampled(
            $cadre,
            $photo,
            0,
            0,
            $depuisX,
            $depuisY,
            self::LARGEUR,
            self::HAUTEUR,
            max(1, $largeurPrise),
            max(1, $hauteurPrise)
        );

        imagedestroy($photo);

        $this->enregistrer($cadre, $destination);
    }

    /**
     * Écrit le JPEG en descendant la qualité par paliers jusqu'à tenir dans le
     * poids visé. En pratique le premier palier suffit ; les suivants ne servent
     * qu'aux photos très texturées, que 82 ne compresse pas assez.
     */
    private function enregistrer(\GdImage $cadre, string $destination): void
    {
        foreach ([82, 70, 60, 50] as $qualite) {
            imagejpeg($cadre, $destination, $qualite);

            if (filesize($destination) <= self::POIDS_VISE) {
                break;
            }
        }

        imagedestroy($cadre);
    }

    /**
     * Place l'image entière au centre du cadre, sur fond blanc.
     */
    private function poserAuCentre(\GdImage $photo, string $destination, int $largeurSource, int $hauteurSource): void
    {
        $facteur = min(self::LARGEUR / $largeurSource, self::HAUTEUR / $hauteurSource, 1.0);

        $largeurCible = max(1, (int) round($largeurSource * $facteur));
        $hauteurCible = max(1, (int) round($hauteurSource * $facteur));

        $cadre = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagefill($cadre, 0, 0, imagecolorallocate($cadre, 255, 255, 255));

        imagecopyresampled(
            $cadre,
            $photo,
            (int) ((self::LARGEUR - $largeurCible) / 2),
            (int) ((self::HAUTEUR - $hauteurCible) / 2),
            0,
            0,
            $largeurCible,
            $hauteurCible,
            $largeurSource,
            $hauteurSource
        );

        imagedestroy($photo);
        $this->enregistrer($cadre, $destination);
    }

    private function ouvrir(string $chemin): \GdImage
    {
        $type = @exif_imagetype($chemin) ?: null;

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_PNG => @imagecreatefrompng($chemin),
            IMAGETYPE_GIF => @imagecreatefromgif($chemin),
            IMAGETYPE_WEBP => @imagecreatefromwebp($chemin),
            default => null,
        };

        if (! $image) {
            throw new \RuntimeException("Format d'image non pris en charge : " . $chemin);
        }

        return $image;
    }

    /**
     * Le fichier réel derrière ce qui est enregistré en base.
     *
     * Les valeurs cohabitent sous plusieurs formes selon leur époque : URL
     * absolue sur le domaine courant, ou chemin relatif. Une URL pointant
     * ailleurs n'est pas rapatriée — aller chercher une image sur un hôte tiers
     * au moment du rendu exposerait la page à sa lenteur et à ses pannes.
     */
    private function fichierSource(?string $valeur): ?string
    {
        if (! $valeur) {
            return null;
        }

        $chemin = $valeur;

        if (str_starts_with($valeur, 'http://') || str_starts_with($valeur, 'https://')) {
            $hote = parse_url($valeur, PHP_URL_HOST);

            if ($hote && $hote !== request()->getHost() && $hote !== parse_url(config('app.url'), PHP_URL_HOST)) {
                return null;
            }

            $chemin = (string) parse_url($valeur, PHP_URL_PATH);
        }

        $absolu = public_path(ltrim(urldecode($chemin), '/'));

        return is_file($absolu) ? $absolu : null;
    }

    private function logo(): string
    {
        return public_path('images/logo.png');
    }

    /**
     * Le dossier des aperçus vit sous public/upload.
     *
     * C'est un lien vers le dossier réellement servi par le serveur web : un
     * fichier écrit ailleurs dans public/ à l'exécution ne serait jamais servi,
     * la racine web étant une copie de la release et non un lien vers elle.
     */
    private function dossierCache(): string
    {
        $dossier = public_path('upload/apercus');

        if (! is_dir($dossier)) {
            mkdir($dossier, 0o755, true);
        }

        return $dossier;
    }

    /**
     * Retire les aperçus devenus obsolètes pour cet objet.
     *
     * Sans ça, chaque changement de photo laisserait derrière lui un fichier que
     * plus rien ne référence, et le dossier grossirait indéfiniment.
     */
    private function purger(string $cle, string $garder): void
    {
        foreach (glob($this->dossierCache() . '/' . $cle . '-*.jpg') ?: [] as $ancien) {
            if ($ancien !== $garder) {
                @unlink($ancien);
            }
        }
    }
}
