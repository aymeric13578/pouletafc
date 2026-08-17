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

    /**
     * Une photo en deux moitiés franches, rouge à gauche et bleue à droite.
     * C'est ce qui permet de distinguer un cadre rempli d'un cadre étiré.
     */
    private function photoDeuxMoities(int $largeur, int $hauteur): string
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagefilledrectangle($image, 0, 0, (int) ($largeur / 2) - 1, $hauteur - 1, imagecolorallocate($image, 220, 20, 20));
        imagefilledrectangle($image, (int) ($largeur / 2), 0, $largeur - 1, $hauteur - 1, imagecolorallocate($image, 20, 20, 220));

        $chemin = public_path('upload/essai-' . uniqid() . '.png');
        imagepng($image, $chemin);
        imagedestroy($image);

        $this->aNettoyer[] = $chemin;

        return $chemin;
    }

    public function test_la_photo_occupe_tout_le_cadre(): void
    {
        // Photo bien plus haute que large : c'est le cas où des bandes blanches
        // apparaissaient de chaque côté, réduisant la photo à un timbre.
        $source = $this->photoDeuxMoities(400, 1200);

        $apercu = (new ImageDePartage())->fabriquer('upload/' . basename($source), 'essai-remplissage');
        $this->aNettoyer[] = $apercu;

        $image = imagecreatefromjpeg($apercu);

        $milieu = (int) (ImageDePartage::HAUTEUR / 2);
        $gauche = imagecolorsforindex($image, imagecolorat($image, 40, $milieu));
        $droite = imagecolorsforindex($image, imagecolorat($image, ImageDePartage::LARGEUR - 40, $milieu));
        $hautGauche = imagecolorsforindex($image, imagecolorat($image, 40, 10));

        imagedestroy($image);

        // Les bords portent la photo, pas du blanc.
        $this->assertGreaterThan(150, $gauche['red'], 'Le bord gauche devrait être rouge, pas blanc.');
        $this->assertLessThan(90, $gauche['blue']);

        $this->assertGreaterThan(150, $droite['blue'], 'Le bord droit devrait être bleu, pas blanc.');
        $this->assertLessThan(90, $droite['red']);

        // Le haut aussi : aucune bande ne subsiste nulle part.
        $this->assertGreaterThan(150, $hautGauche['red']);
    }

    public function test_la_photo_n_est_pas_etiree(): void
    {
        // La frontière entre les deux moitiés doit rester au milieu exact du
        // cadre. Un étirement horizontal la décalerait.
        $source = $this->photoDeuxMoities(1000, 1000);

        $apercu = (new ImageDePartage())->fabriquer('upload/' . basename($source), 'essai-non-etire');
        $this->aNettoyer[] = $apercu;

        $image = imagecreatefromjpeg($apercu);
        $milieu = (int) (ImageDePartage::HAUTEUR / 2);

        $avantFrontiere = imagecolorsforindex($image, imagecolorat($image, (int) (ImageDePartage::LARGEUR / 2) - 20, $milieu));
        $apresFrontiere = imagecolorsforindex($image, imagecolorat($image, (int) (ImageDePartage::LARGEUR / 2) + 20, $milieu));

        imagedestroy($image);

        $this->assertGreaterThan(150, $avantFrontiere['red']);
        $this->assertGreaterThan(150, $apresFrontiere['blue']);
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
