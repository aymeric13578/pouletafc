<?php

namespace Tests\Feature;

use App\Support\ImageDePartage;
use Tests\TestCase;

/**
 * WhatsApp retélécharge l'image d'aperçu et renonce au-delà d'un certain poids :
 * le lien partagé s'affiche alors sans image. Une photo prise au téléphone pèse
 * couramment plusieurs mégaoctets — d'où cette normalisation.
 */
class ImageDePartageTest extends TestCase
{
    private array $aNettoyer = [];

    protected function tearDown(): void
    {
        foreach ($this->aNettoyer as $fichier) {
            @unlink($fichier);
        }

        parent::tearDown();
    }

    /**
     * Une photo lourde et bruitée, comme celle qui sort d'un téléphone : le
     * bruit est ce qui résiste à la compression, une image unie ne prouverait
     * rien.
     */
    private function photoLourde(int $largeur = 3000, int $hauteur = 2000): string
    {
        $image = imagecreatetruecolor($largeur, $hauteur);

        for ($x = 0; $x < $largeur; $x += 2) {
            for ($y = 0; $y < $hauteur; $y += 2) {
                $couleur = imagecolorallocate($image, ($x * 7) % 256, ($y * 13) % 256, ($x + $y) % 256);
                imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $couleur);
            }
        }

        $chemin = public_path('upload/essai-' . uniqid() . '.jpg');
        imagejpeg($image, $chemin, 100);
        imagedestroy($image);

        $this->aNettoyer[] = $chemin;

        return $chemin;
    }

    public function test_une_photo_lourde_est_ramenee_sous_le_seuil_des_messageries(): void
    {
        $source = $this->photoLourde();
        $this->assertGreaterThan(600_000, filesize($source), 'La photo de départ doit être lourde pour que le test ait un sens.');

        $apercu = (new ImageDePartage())->fabriquer('upload/' . basename($source), 'essai-poids');
        $this->aNettoyer[] = $apercu;

        $this->assertLessThanOrEqual(300_000, filesize($apercu));
    }

    public function test_l_apercu_a_le_format_attendu_par_les_robots(): void
    {
        $source = $this->photoLourde();

        $apercu = (new ImageDePartage())->fabriquer('upload/' . basename($source), 'essai-format');
        $this->aNettoyer[] = $apercu;

        [$largeur, $hauteur] = getimagesize($apercu);

        $this->assertSame(ImageDePartage::LARGEUR, $largeur);
        $this->assertSame(ImageDePartage::HAUTEUR, $hauteur);
    }

    public function test_la_photo_n_est_pas_deformee(): void
    {
        // Une image nettement plus haute que large : si le cadrage étirait au
        // lieu de conserver les proportions, le carré rouge deviendrait un
        // rectangle et la couleur déborderait de sa zone.
        $source = $this->photoLourde(800, 2400);

        $apercu = (new ImageDePartage())->fabriquer('upload/' . basename($source), 'essai-proportions');
        $this->aNettoyer[] = $apercu;

        $image = imagecreatefromjpeg($apercu);

        // Les bords gauche et droit doivent rester blancs : la photo, plus haute
        // que large, ne peut occuper toute la largeur du cadre.
        $bord = imagecolorsforindex($image, imagecolorat($image, 5, (int) (ImageDePartage::HAUTEUR / 2)));

        imagedestroy($image);

        $this->assertGreaterThan(240, $bord['red']);
        $this->assertGreaterThan(240, $bord['green']);
        $this->assertGreaterThan(240, $bord['blue']);
    }

    public function test_une_source_introuvable_retombe_sur_le_logo(): void
    {
        $apercu = (new ImageDePartage())->fabriquer('upload/inexistante-' . uniqid() . '.jpg', 'essai-absente');
        $this->aNettoyer[] = $apercu;

        $this->assertFileExists($apercu);
        [$largeur, $hauteur] = getimagesize($apercu);
        $this->assertSame(ImageDePartage::LARGEUR, $largeur);
        $this->assertSame(ImageDePartage::HAUTEUR, $hauteur);
    }

    public function test_le_second_appel_reutilise_le_fichier_deja_fabrique(): void
    {
        $source = $this->photoLourde(1200, 900);
        $service = new ImageDePartage();

        $premier = $service->fabriquer('upload/' . basename($source), 'essai-cache');
        $this->aNettoyer[] = $premier;
        $empreinte = filemtime($premier);

        $second = $service->fabriquer('upload/' . basename($source), 'essai-cache');

        $this->assertSame($premier, $second);
        $this->assertSame($empreinte, filemtime($second), "L'aperçu ne doit pas être refabriqué à chaque partage.");
    }
}
