<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\order_detail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Travail proposé à l'agent sur son accueil.
 *
 * Les deux listes remontaient tout depuis l'ouverture du service : des dizaines
 * de demandes vieilles de plusieurs mois, jamais closes, au milieu desquelles
 * les vraies passaient inaperçues. Elles sont désormais bornées à la journée.
 */
class TravailDuJourTest extends TestCase
{
    private const CLANDO = '/api/v1.0/getClandoWithoutAgent';
    private const COMMANDES = '/api/v1.0/getAllWithoutSellerOrder';

    /** created_at n'est pas dans $fillable : il faut le query builder. */
    private function ecrire(string $table, int $id, array $valeurs): void
    {
        DB::table($table)->where('id', $id)->update($valeurs);
    }

    private function refsRenvoyees(string $url): array
    {
        return collect($this->getJson($url)->assertOk()->json('data') ?? [])
            ->pluck('ref')
            ->all();
    }

    public function test_une_course_du_jour_est_proposee(): void
    {
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $course->status, 'id_agent' => $course->id_agent, 'created_at' => $course->created_at];

        $this->ecrire('clando', $course->id, [
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        $this->assertContains($course->ref, $this->refsRenvoyees(self::CLANDO));

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_une_course_d_hier_n_est_plus_proposee(): void
    {
        /*
         * C'est le cœur du problème signalé : des courses restées « want »
         * depuis des mois encombraient l'écran de l'agent.
         */
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $course->status, 'id_agent' => $course->id_agent, 'created_at' => $course->created_at];

        $this->ecrire('clando', $course->id, [
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now()->subDays(2),
        ]);

        $this->assertNotContains($course->ref, $this->refsRenvoyees(self::CLANDO));

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_une_commande_du_jour_est_proposee(): void
    {
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $commande->status, 'id_agent' => $commande->id_agent, 'created_at' => $commande->created_at];

        $this->ecrire('order_details', $commande->id, [
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        $this->assertContains($commande->ref, $this->refsRenvoyees(self::COMMANDES));

        $this->ecrire('order_details', $commande->id, $etat);
    }

    public function test_une_commande_ancienne_n_est_plus_proposee(): void
    {
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $commande->status, 'id_agent' => $commande->id_agent, 'created_at' => $commande->created_at];

        $this->ecrire('order_details', $commande->id, [
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now()->subMonths(3),
        ]);

        $this->assertNotContains($commande->ref, $this->refsRenvoyees(self::COMMANDES));

        $this->ecrire('order_details', $commande->id, $etat);
    }

    public function test_une_commande_deja_livree_n_est_pas_proposee(): void
    {
        /*
         * L'ancien filtre était « status != pending » : il écartait les commandes
         * non préparées mais laissait passer Success et failed. Une commande déjà
         * livrée, dont l'agent n'avait pas été enregistré, s'affichait donc comme
         * du travail à prendre.
         */
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $commande->status, 'id_agent' => $commande->id_agent, 'created_at' => $commande->created_at];

        foreach (['Success', 'failed'] as $statut) {
            $this->ecrire('order_details', $commande->id, [
                'status' => $statut,
                'id_agent' => null,
                'created_at' => now(),
            ]);

            $this->assertNotContains(
                $commande->ref,
                $this->refsRenvoyees(self::COMMANDES),
                "Une commande « $statut » ne doit pas être proposée à un agent.",
            );
        }

        $this->ecrire('order_details', $commande->id, $etat);
    }

    public function test_une_commande_deja_prise_n_est_pas_proposee(): void
    {
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $commande->status, 'id_agent' => $commande->id_agent, 'created_at' => $commande->created_at];

        $this->ecrire('order_details', $commande->id, [
            'status' => 'want',
            'id_agent' => 173,
            'created_at' => now(),
        ]);

        $this->assertNotContains($commande->ref, $this->refsRenvoyees(self::COMMANDES));

        $this->ecrire('order_details', $commande->id, $etat);
    }

    public function test_la_journee_est_celle_du_cameroun(): void
    {
        /*
         * Les dates sont stockées en UTC. Sans conversion, la liste basculerait à
         * minuit UTC, soit une heure du matin à Garoua : les courses de la soirée
         * disparaîtraient de l'écran des agents encore en service.
         *
         * On place une course à 00 h 30 heure locale — soit 23 h 30 UTC la veille.
         * Un filtre écrit en UTC la manquerait.
         */
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $course->status, 'id_agent' => $course->id_agent, 'created_at' => $course->created_at];

        $minuitTrente = now()->setTimezone('Africa/Douala')->startOfDay()->addMinutes(30);

        // Le test n'a de sens que si cet instant est déjà passé.
        if ($minuitTrente->isFuture()) {
            $this->markTestSkipped('Il est encore avant 00 h 30 à Garoua.');
        }

        $this->ecrire('clando', $course->id, [
            'status' => 'want',
            'id_agent' => null,
            'created_at' => $minuitTrente->copy()->utc(),
        ]);

        $this->assertContains(
            $course->ref,
            $this->refsRenvoyees(self::CLANDO),
            'Une course passée juste après minuit à Garoua appartient à la journée en cours.',
        );

        $this->ecrire('clando', $course->id, $etat);
    }
}
