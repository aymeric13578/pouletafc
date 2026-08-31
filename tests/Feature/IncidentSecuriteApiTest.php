<?php

namespace Tests\Feature;

use App\Models\IncidentSecurite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Boutons "Enregistrer"/"Signaler" de l'écran de course — voir
 * IncidentSecuriteController.
 */
class IncidentSecuriteApiTest extends TestCase
{
    protected function tearDown(): void
    {
        IncidentSecurite::query()->delete();
        parent::tearDown();
    }

    public function test_signalerCourse_cree_une_alerte_nouvelle(): void
    {
        $this->postJson('/api/v1.0/signalerCourse', [
            'id_clando' => 42,
            'id_client' => 1,
            'id_agent' => 2,
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseHas('incidents_securite', [
            'id_clando' => 42,
            'type' => IncidentSecurite::SIGNALEMENT,
            'statut' => IncidentSecurite::NOUVEAU,
        ]);
    }

    /*
     | Les deux tests suivants vérifient la requête que payload() construit
     | pour "alertes_securite", directement plutôt qu'en passant par
     | /clando/flux : cette route est protégée par un cookie signé QR
     | (KioskLock, voir app/Support/KioskLock.php) que le client de test
     | HTTP ne sait pas fabriquer sans une gymnastique d'EncryptCookies
     | étrangère à ce qu'on cherche à vérifier ici — la présence/absence
     | d'une alerte dans le flux, pas le verrou de l'écran lui-même (déjà
     | couvert par ClandoBoardTest).
     */
    private function alertesActives(): \Illuminate\Support\Collection
    {
        return IncidentSecurite::where('type', IncidentSecurite::SIGNALEMENT)
            ->where('statut', IncidentSecurite::NOUVEAU)
            ->orderByDesc('id')
            ->get(['id', 'id_clando']);
    }

    public function test_signalerCourse_apparait_dans_le_flux_du_mur(): void
    {
        $reponse = $this->postJson('/api/v1.0/signalerCourse', ['id_clando' => 43])
            ->assertOk()->json();

        $alertes = $this->alertesActives();

        $this->assertTrue($alertes->contains('id', $reponse['data']['id']));
        $this->assertSame(43, $alertes->firstWhere('id', $reponse['data']['id'])->id_clando);
    }

    public function test_acquitter_une_alerte_la_retire_du_flux(): void
    {
        $reponse = $this->postJson('/api/v1.0/signalerCourse', ['id_clando' => 44])
            ->assertOk()->json();
        $idIncident = $reponse['data']['id'];

        $this->post("/clando/alerte/{$idIncident}/acquitter")->assertOk();

        $this->assertFalse($this->alertesActives()->contains('id', $idIncident));
    }

    public function test_enregistrerAudioCourse_stocke_hors_du_dossier_public(): void
    {
        Storage::fake('incidents-securite');

        $audio = UploadedFile::fake()->create('course.mp3', 100, 'audio/mpeg');

        $reponse = $this->postJson('/api/v1.0/enregistrerAudioCourse', [
            'id_clando' => 45,
            'audio' => $audio,
        ])->assertOk()->json();

        $incident = IncidentSecurite::findOrFail($reponse['data']['id']);

        $this->assertNotNull($incident->audio_path);
        Storage::disk('incidents-securite')->assertExists($incident->audio_path);

        // Jamais dans public_path('upload') : ce dossier est servi sans la
        // moindre authentification (CLAUDE.md règle 8).
        $this->assertStringNotContainsString('upload', $incident->audio_path);
    }

    /**
     * Régression : l'encodeur AAC LC d'Android (package `record`, voir
     * clando.dart) écrit un conteneur MP4 avec la marque ftyp "mp42/isom",
     * sans la marque "M4A " — le détecteur de type par contenu classe donc
     * ce fichier réel en video/mp4, jamais audio/mp4, même s'il ne contient
     * qu'une piste audio. UploadedFile::fake()->create() avec un mimetype
     * déclaré ne l'aurait jamais détecté : createWithContent() force le
     * vrai sniffing par contenu, comme en production.
     */
    public function test_enregistrerAudioCourse_accepte_un_vrai_enregistrement_android(): void
    {
        Storage::fake('incidents-securite');

        $enteteFtypReelle = hex2bin(
            '00000018' . '66747970' . '6d703432' . '00000000' . '69736f6d' . '6d703432' .
            '00000001' . '6d646174'
        );
        $audio = UploadedFile::fake()->createWithContent('course.m4a', $enteteFtypReelle);

        $reponse = $this->postJson('/api/v1.0/enregistrerAudioCourse', [
            'id_clando' => 47,
            'audio' => $audio,
        ]);

        $reponse->assertOk();
        $incident = IncidentSecurite::findOrFail($reponse->json('data.id'));
        Storage::disk('incidents-securite')->assertExists($incident->audio_path);
    }

    public function test_enregistrerAudioCourse_refuse_un_fichier_qui_n_est_pas_de_l_audio(): void
    {
        $faux = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

        $this->postJson('/api/v1.0/enregistrerAudioCourse', [
            'id_clando' => 46,
            'audio' => $faux,
        ])->assertStatus(422);
    }
}
