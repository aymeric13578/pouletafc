<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Tests\TestCase;

class MaBoutiqueAuthentificationTest extends TestCase
{
    private ?User $marchand = null;
    private ?Shop $boutique = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Le schéma local de `shops` peut être en retard sur la production
        // (colonnes ajoutées à la main sur le serveur, absentes de la
        // migration trackée — voir Global Constraints). Sans `id_user`, ce
        // test ne peut vérifier rien d'utile.
        if (! \Illuminate\Support\Facades\Schema::hasColumn('shops', 'id_user')) {
            $this->markTestSkipped("Colonne `shops.id_user` absente de cette base : schéma incomplet.");
        }
    }

    protected function tearDown(): void
    {
        $this->boutique?->delete();
        if ($this->marchand) {
            $this->marchand->tokens()->delete();
            $this->marchand->delete();
        }

        parent::tearDown();
    }

    public function test_getMyShop_fonctionne_toujours_avec_un_jeton_valide(): void
    {
        $this->marchand = User::factory()->create(['role' => 'user', 'status' => 'Success']);
        $this->boutique = Shop::create([
            'shop_name' => 'Boutique test authentification ' . uniqid(),
            'id_user' => $this->marchand->id,
            'status' => 'Success',
        ]);

        $jeton = $this->marchand->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/getMyShop?token=' . $jeton)
            ->assertOk()
            ->assertJsonPath('response', 200);
    }

    public function test_getMyShop_renvoie_data_null_avec_un_jeton_invalide(): void
    {
        // Comportement actuel et volontaire de getMyShop (ligne 87-91) : un
        // jeton invalide et un compte sans boutique produisent la même
        // réponse "response: 200, data: null" — l'application masque
        // simplement l'entrée boutique, sans distinguer les deux cas.
        $this->getJson('/api/v1.0/getMyShop?token=jeton-invalide')
            ->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data', null);
    }
}
