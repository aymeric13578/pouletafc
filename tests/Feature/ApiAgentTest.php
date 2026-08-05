<?php

namespace Tests\Feature;

use App\Models\order_detail;
use Tests\TestCase;

/**
 * Points d'entrée consommés par l'application des agents.
 */
class ApiAgentTest extends TestCase
{
    /** Bornes de la journée camerounaise, exprimées en UTC comme en base. */
    private function bornesDuJour(): array
    {
        $debut = now()->setTimezone('Africa/Douala')->startOfDay();

        return [$debut->copy()->utc(), $debut->copy()->endOfDay()->utc()];
    }

    public function test_getAllOrder_ne_renvoie_que_les_commandes_du_jour(): void
    {
        /*
         * Sans borne de date, l'écran de l'agent accumulait toutes les commandes
         * jamais prises depuis l'ouverture du service.
         */
        $reponse = $this->getJson('/api/v1.0/getAllOrder');
        $reponse->assertOk();

        [$debut, $fin] = $this->bornesDuJour();

        foreach ($reponse->json('data') ?? [] as $commande) {
            $this->assertGreaterThanOrEqual(
                $debut->timestamp,
                strtotime($commande['created_at']),
                'Une commande antérieure à aujourd\'hui ne doit pas être proposée.'
            );
            $this->assertLessThanOrEqual($fin->timestamp, strtotime($commande['created_at']));
        }
    }

    public function test_getAllOrder_inclut_les_colis_prets(): void
    {
        // « Colis prêt » place la commande en "waiting" : elle doit rester visible
        // par les agents, c'est justement le moment où on veut qu'ils la voient.
        $reponse = $this->getJson('/api/v1.0/getAllOrder');
        $reponse->assertOk();

        foreach ($reponse->json('data') ?? [] as $commande) {
            $this->assertContains($commande['status'], ['pending', 'waiting']);
        }
    }

    public function test_l_historique_se_limite_aux_commandes_prises_par_l_agent(): void
    {
        $agentId = order_detail::whereNotNull('id_agent')->value('id_agent');

        if (! $agentId) {
            $this->markTestSkipped('Aucune commande rattachée à un agent.');
        }

        $reponse = $this->getJson('/api/v1.0/getAllOrderWithoutCondition?id_user=' . $agentId);
        $reponse->assertOk();

        foreach ($reponse->json('data') ?? [] as $commande) {
            $this->assertSame((int) $agentId, (int) $commande['id_agent']);
        }
    }

    public function test_l_historique_reste_complet_sans_parametre(): void
    {
        // Compatibilité : les clients existants qui n'envoient pas id_user ne
        // doivent pas se retrouver avec un écran vide.
        $reponse = $this->getJson('/api/v1.0/getAllOrderWithoutCondition');

        $reponse->assertOk();
        $this->assertGreaterThan(0, count($reponse->json('data') ?? []));
    }
}
