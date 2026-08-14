<?php

namespace Tests\Feature;

use App\Models\order_detail;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Demande de course coursier depuis l'application cliente.
 *
 * L'écran poste vers storeDeliveryOrder depuis des mois, mais la route n'avait
 * jamais été écrite : le serveur répondait « méthode non autorisée » et l'écran
 * restait bloqué. Aucune demande n'a jamais abouti depuis l'application.
 */
class CoursierTest extends TestCase
{
    private const URL = '/api/v1.0/storeDeliveryOrder';

    private array $creees = [];

    protected function tearDown(): void
    {
        foreach ($this->creees as $ref) {
            $commande = order_detail::where('ref', $ref)->first();

            if ($commande?->image) {
                @unlink(public_path('upload/' . $commande->image));
            }

            DB::table('order_details')->where('ref', $ref)->delete();
        }

        parent::tearDown();
    }

    private function charge(array $remplacements = []): array
    {
        return array_merge([
            'id_user' => User::query()->value('id'),
            'latitude' => 9.3006,
            'longitude' => 13.3979,
            'latDestination' => 9.2944,
            'lonDestination' => 13.4012,
            'address' => 'Rond-point Grand Marché',
            'depart' => 'Sodecoton parc',
            'price' => 1500,
            'delivery_fees' => 500,
            'phone_customer' => '690123456',
            'delivery_type' => 'Petit colis',
            'payment_method' => 'ESPECES',
            // L'application envoie cette valeur, que la colonne refuse.
            'status_paiement' => 'unpaid',
            'note' => 'Expéditeur: Ali | Destinataire: Fatou | Instructions: 2e étage',
        ], $remplacements);
    }

    private function retenir(?string $ref): void
    {
        if ($ref) {
            $this->creees[] = $ref;
        }
    }

    public function test_une_demande_de_coursier_est_enregistree(): void
    {
        $reponse = $this->postJson(self::URL, $this->charge());

        $reponse->assertOk();
        // L'écran considère « code » à 100 comme un succès et lit « data ».
        $this->assertSame(100, $reponse->json('code'));

        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $commande = order_detail::where('ref', $ref)->firstOrFail();

        $this->assertSame('pending', $commande->status);
        $this->assertSame(1500, (int) $commande->price);
        $this->assertSame('LIVRAISON', $commande->reception_mode);
        $this->assertNotNull($reponse->json('data.delivery_code'));
    }

    public function test_le_depart_et_l_arrivee_ne_se_confondent_pas(): void
    {
        /*
         * Le colis part d'un point et va à un autre. Le départ occupe les
         * colonnes latShop, celles que tient la boutique sur une commande
         * ordinaire ; l'arrivée occupe latitude/longitude.
         */
        $reponse = $this->postJson(self::URL, $this->charge());
        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $commande = order_detail::where('ref', $ref)->firstOrFail();

        $this->assertEqualsWithDelta(9.3006, (float) $commande->latShop, 0.001, 'départ');
        $this->assertEqualsWithDelta(9.2944, (float) $commande->latitude, 0.001, 'arrivée');
        $this->assertSame('Sodecoton parc', $commande->depart);
        $this->assertSame('Rond-point Grand Marché', $commande->address);
    }

    public function test_le_statut_de_paiement_envoye_par_l_application_est_traduit(): void
    {
        /*
         * L'application envoie « unpaid ». La colonne est un
         * enum('pending','Success','failed') : non traduite, l'insertion
         * échouait et la demande était perdue.
         */
        $reponse = $this->postJson(self::URL, $this->charge());
        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $this->assertSame('pending', order_detail::where('ref', $ref)->value('status_paiement'));

        // payment_method est lui aussi un enum : « ESPECES » n'y figure pas.
        $this->assertSame('LIVRAISON', order_detail::where('ref', $ref)->value('payment_method'));
    }

    public function test_la_note_du_client_est_conservee(): void
    {
        // Expéditeur, destinataire et instructions : c'est ce dont le livreur a
        // besoin pour remettre le colis à la bonne personne.
        $reponse = $this->postJson(self::URL, $this->charge());
        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $this->assertStringContainsString('Fatou', order_detail::where('ref', $ref)->value('note'));
    }

    public function test_la_photo_du_colis_est_enregistree_et_servie(): void
    {
        $reponse = $this->post(self::URL, $this->charge([
            'image' => UploadedFile::fake()->image('colis.jpg', 640, 480),
        ]));

        $reponse->assertOk();
        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $nom = order_detail::where('ref', $ref)->value('image');

        $this->assertNotNull($nom, 'La photo doit être rattachée à la commande.');
        $this->assertFileExists(public_path('upload/' . $nom), 'Le fichier doit être rangé avec les autres images.');
        $this->assertStringContainsString('/upload/' . $nom, $reponse->json('data.image_url'));
    }

    public function test_une_demande_sans_photo_passe_quand_meme(): void
    {
        // La photo est facultative : un client pressé n'en met pas.
        $reponse = $this->postJson(self::URL, $this->charge());
        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $this->assertNull(order_detail::where('ref', $ref)->value('image'));
    }

    public function test_une_demande_incomplete_est_refusee(): void
    {
        $this->postJson(self::URL, [])->assertStatus(422);

        $this->postJson(self::URL, $this->charge(['address' => '']))->assertStatus(422);
        $this->postJson(self::URL, $this->charge(['latDestination' => 'ici']))->assertStatus(422);
    }

    public function test_une_erreur_de_saisie_revient_en_json_meme_sans_en_tete_accept(): void
    {
        /*
         * L'application poste avec MultipartRequest, qui ne pose aucun en-tête
         * Accept. Sans garde, Laravel en conclut qu'il parle à un navigateur et
         * répond par une redirection : l'application recevait du HTML, jsonDecode
         * levait une exception, et l'utilisateur voyait « erreur » sans jamais
         * savoir quel champ était en cause.
         */
        $reponse = $this->call('POST', self::URL, ['ref' => 'TEST'], [], [], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertSame(422, $reponse->getStatusCode(), 'Une redirection ici casse l\'application.');
        $this->assertJson($reponse->getContent());
    }

    public function test_un_numero_avec_indicatif_ne_deborde_pas_de_la_colonne(): void
    {
        /*
         * phone_customer est un int(11) : un numéro préfixé de son indicatif
         * déborderait et serait tronqué en silence.
         */
        $reponse = $this->postJson(self::URL, $this->charge(['phone_customer' => '+237690123456']));
        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $this->assertSame(690123456, (int) order_detail::where('ref', $ref)->value('phone_customer'));
    }

    public function test_la_course_apparait_sur_le_mur_des_commandes(): void
    {
        // Une course coursier doit se voir au comptoir comme une commande.
        $reponse = $this->postJson(self::URL, $this->charge());
        $ref = $reponse->json('data.ref');
        $this->retenir($ref);

        $charge = $this->getJson('/commandes/flux')->assertOk()->json();

        $this->assertContains($ref, collect($charge['orders'])->pluck('ref')->all());
    }
}
