<?php

namespace App\Support;

use App\Fonction\Fonction;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Attribution d'une course ou d'une commande à un agent depuis le tableau de bord.
 *
 * Jusqu'ici, seul l'agent pouvait se saisir d'une demande depuis son téléphone
 * (takeClandoCommand / takeOrderCommand). Quand personne ne prenait une course,
 * le comptoir n'avait aucun moyen d'agir : il fallait appeler un agent et le
 * convaincre d'ouvrir l'application.
 *
 * Cette classe rejoue exactement les règles de ces deux endpoints, pour qu'une
 * attribution faite au comptoir laisse la base dans le même état qu'une prise en
 * charge faite depuis le téléphone. Ne pas les rejouer produirait des courses
 * incohérentes : un agent marqué libre alors qu'il roule, ou une commission
 * prélevée sur un solde absent.
 *
 * Une différence assumée avec l'application : le contrôle de solde y renvoie
 * « rechargez votre compte » à l'agent. Ici c'est un opérateur qui agit, et le
 * message doit lui dire de qui il s'agit et combien il manque.
 */
class AttributionAgent
{
    /**
     * Attribue $cible à l'agent $idAgent.
     *
     * @param  Model  $cible  une course (Clando) ou une commande (order_detail)
     * @param  int  $idAgent  identifiant utilisateur de l'agent, pas l'id de la ligne agents
     * @param  float  $montantARegler  ce que l'agent doit pouvoir couvrir : la commission
     *                                 pour une course, le prix pour une commande — c'est
     *                                 ce que font respectivement takeClandoCommand et
     *                                 takeOrderCommand.
     * @return array{ok: bool, message: string, code: int}
     */
    public function attribuer(Model $cible, int $idAgent, float $montantARegler): array
    {
        $agent = Agent::where('id_user', $idAgent)->first();

        if (! $agent) {
            return $this->echec("Cet utilisateur n'est pas un agent.", 422);
        }

        $solde = (new Fonction())->solde($idAgent);

        if ($solde['solde'] < $montantARegler) {
            $manque = $montantARegler - $solde['solde'];

            return $this->echec(
                sprintf(
                    'Solde insuffisant pour %s : %s FCFA disponibles, %s requis (il manque %s).',
                    $agent->agent_name ?? 'cet agent',
                    number_format((float) $solde['solde'], 0, ',', ' '),
                    number_format($montantARegler, 0, ',', ' '),
                    number_format($manque, 0, ',', ' '),
                ),
                422,
            );
        }

        /*
         | Tout se joue dans une transaction, et la reprise de la cible se fait
         | avec un verrou.
         |
         | Deux opérateurs devant deux écrans peuvent cliquer sur la même course
         | à la même seconde, et l'agent peut la prendre depuis son téléphone au
         | même moment. Sans verrou, les deux lisent id_agent à null, les deux
         | attribuent, et le second écrase le premier : la course part avec un
         | agent qui ne sait pas qu'il l'a, pendant que l'autre roule pour rien.
         */
        return DB::transaction(function () use ($cible, $idAgent, $agent) {
            $frais = $cible->newQuery()->lockForUpdate()->find($cible->getKey());

            if (! $frais) {
                return $this->echec('Cet élément n\'existe plus.', 404);
            }

            if ($frais->id_agent !== null) {
                // Même garde que dans l'application : « désolé cette commande est
                // déjà prise ».
                return $this->echec('Déjà attribué à un agent.', 409);
            }

            /*
             | Même garde que takeClandoCommand/takeOrderCommand : une course ou
             | commande annulée entre-temps (par le client, le comptoir, ou déjà
             | close) ne se réattribue pas. Sans ce contrôle, l'attribution
             | manuelle depuis le tableau de bord restait le seul des trois
             | chemins (mobile, ici, carte) à ignorer une annulation — un
             | opérateur pouvait attribuer un agent à une course que le client
             | venait d'annuler.
             */
            if (AnnulationDeCommande::estAnnulee($frais)) {
                $motif = $frais->cancel_reason ?? null;

                return $this->echec($motif ? 'Annulé : '.$motif : 'Cet élément a été annulé.', 409);
            }

            if (! in_array($frais->status, ['want', 'pending'], true)) {
                return $this->echec('Cet élément n\'est plus à prendre.', 409);
            }

            $frais->update([
                'id_agent' => $idAgent,
                'status' => 'process',
                'matricule_vehicule' => $agent->matricule_vehicule,
            ]);

            // L'agent n'est plus disponible : c'est ce drapeau que consulte
            // l'application pour décider de lui proposer une course ou non.
            Agent::where('id_user', $idAgent)->update(['freeStatus' => 0]);

            return [
                'ok' => true,
                'message' => sprintf('Attribué à %s.', $agent->agent_name ?? 'l\'agent'),
                'code' => 200,
            ];
        });
    }

    /**
     * Agents que l'opérateur peut choisir, du plus pertinent au moins pertinent.
     *
     * On ne se limite pas aux agents libres : un opérateur doit pouvoir voir
     * qu'un agent est occupé plutôt que de le chercher en vain dans une liste où
     * il n'apparaît pas. Le tri fait remonter ceux qui sont en service et libres.
     *
     * @return array<int, array<string, mixed>>
     */
    public function agentsDisponibles(): array
    {
        /*
         | Solde et disponibilité en une requête plutôt qu'un appel à Fonction::solde()
         | par agent : celui-ci lance quatre requêtes agrégées, soit quarante-quatre
         | requêtes pour onze agents, à chaque ouverture de la liste.
         */
        return Agent::query()
            ->leftJoin('users', 'users.id', '=', 'agents.id_user')
            ->select([
                'agents.id_user',
                'agents.agent_name',
                'agents.freeStatus',
                'agents.matricule_vehicule',
                'agents.vehicule',
                'users.phone',
                'users.in_activity',
            ])
            ->whereNotNull('agents.id_user')
            ->orderByDesc('users.in_activity')
            ->orderByDesc('agents.freeStatus')
            ->orderBy('agents.agent_name')
            ->get()
            ->map(fn ($a) => [
                'id_user' => (int) $a->id_user,
                'name' => $a->agent_name,
                'phone' => $a->phone,
                'vehicule' => $a->vehicule,
                'matricule' => $a->matricule_vehicule,
                'libre' => (int) $a->freeStatus === 1,
                'en_service' => (int) $a->in_activity === 1,
            ])
            ->all();
    }

    /**
     * Agents candidats pour UNE course précise, classés par DistributionScore
     * (ETA/distance/qualité/fiabilité/acceptation/priorité) — contrairement à
     * agentsDisponibles() qui liste tout le monde sans tenir compte d'où se
     * trouve la course. N'appelle rien de plus lourd qu'une ouverture de menu
     * d'attribution : pas de recalcul à chaque poll de la carte.
     *
     * @param  Model  $cible  une course (Clando) ou une commande (order_detail)
     * @return array<int, array<string, mixed>>
     */
    public function agentsClasses(Model $cible): array
    {
        $candidats = DistributionScore::candidatsEligibles($cible);

        if ($candidats->isEmpty()) {
            return [];
        }

        $classement = DistributionScore::classerCandidats($cible, $candidats);

        $infos = Agent::query()
            ->leftJoin('users', 'users.id', '=', 'agents.id_user')
            ->whereIn('agents.id_user', $candidats->pluck('id_user'))
            ->select(['agents.id_user', 'agents.agent_name', 'agents.matricule_vehicule', 'agents.vehicule', 'users.phone'])
            ->get()
            ->keyBy('id_user');

        return collect($classement)
            ->map(function (array $c) use ($infos) {
                $agent = $infos[$c['id_user']] ?? null;

                return [
                    'id_user' => $c['id_user'],
                    'name' => $agent->agent_name ?? ('Agent #'.$c['id_user']),
                    'phone' => $agent->phone ?? null,
                    'vehicule' => $agent->vehicule ?? null,
                    'matricule' => $agent->matricule_vehicule ?? null,
                ] + $c;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{ok: bool, message: string, code: int}
     */
    private function echec(string $message, int $code): array
    {
        return ['ok' => false, 'message' => $message, 'code' => $code];
    }
}
