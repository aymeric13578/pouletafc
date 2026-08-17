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
        $destination = $this->dossierCache() . '/' . $cle . '-' . $signature . '.jpg';

        if (is_file($destination)) {
            return $destination;
        }

        try {
            $this->composer($origine, $destination);
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
     * Dessine la photo au centre d'un cadre 1200 × 630, sur fond blanc.
     *
     * Le cadrage conserve les proportions plutôt que de rogner : une brochette
     * coupée en deux dans l'aperçu dessert le produit plus qu'une bande blanche.
     */
    private function composer(string $origine, string $destination): void
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('GD absent : aucune image ne peut être redimensionnée.');
        }

        $photo = $this->ouvrir($origine);

        $largeurSource = imagesx($photo);
        $hauteurSource = imagesy($photo);

        $facteur = min(self::LARGEUR / $largeurSource, self::HAUTEUR / $hauteurSource);

        // Une petite image n'est pas agrandie : l'étirer ne ferait qu'exposer
        // ses défauts, et son poids n'a jamais posé problème.
        $facteur = min($facteur, 1.0);

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

        // On descend la qualité par paliers jusqu'à tenir dans le poids visé.
        // En pratique le premier palier suffit ; les suivants ne servent que
        // pour les photos très texturées, où 82 ne compresse pas assez.
        foreach ([82, 70, 60, 50] as $qualite) {
            imagejpeg($cadre, $destination, $qualite);

            if (filesize($destination) <= self::POIDS_VISE) {
                break;
            }
        }

        imagedestroy($cadre);
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
