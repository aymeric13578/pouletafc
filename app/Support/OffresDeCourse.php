<?php

namespace App\Support;

use App\Models\Clando;
use App\Models\Parameter;
use App\Models\order_detail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Calcule et enregistre les vagues d'offre d'une course, dès qu'elle passe
 * "want" — le seul point d'écriture de la table course_offer_waves.
 *
 * Avant ceci, getActiveCommand diffusait la même course à tous les agents
 * libres en même temps (premier arrivé, premier servi, sans lien avec la
 * qualité de service ou la distance). Les agents les mieux classés par
 * DistributionScore pour CETTE course voient désormais l'offre en premier
 * (vague 1) ; si personne ne la prend, elle s'ouvre progressivement aux
 * suivants — la course de vitesse "premier qui appuie sur Prendre gagne"
 * reste inchangée, seul le moment où chacun voit l'offre change.
 */
class OffresDeCourse
{
    /**
     * @param  Clando|order_detail  $cible
     */
    public static function calculerVagues(Model $cible): void
    {
        $candidats = DistributionScore::candidatsEligibles($cible);

        $idClando = $cible instanceof Clando ? $cible->id : null;
        $idOrder = $cible instanceof order_detail ? $cible->id : null;

        DB::transaction(function () use ($cible, $candidats, $idClando, $idOrder) {
            // Idempotent : un recalcul (ex. si le déclencheur est appelé deux
            // fois par erreur) remplace simplement les lignes existantes
            // plutôt que de les dupliquer ou de tenter un diff plus fragile.
            DB::table('course_offer_waves')
                ->when($idClando, fn ($q) => $q->where('id_clando', $idClando))
                ->when($idOrder, fn ($q) => $q->where('id_order', $idOrder))
                ->delete();

            if ($candidats->isEmpty()) {
                return;
            }

            $classement = DistributionScore::classerCandidats($cible, $candidats);
            $tailleVague = self::tailleVague();
            $delaiSecondes = self::delaiVagueSecondes();
            $maintenant = now();

            $lignes = collect($classement)->values()->map(function (array $c, int $index) use (
                $idClando, $idOrder, $tailleVague, $delaiSecondes, $maintenant
            ) {
                $vague = intdiv($index, $tailleVague) + 1;

                return [
                    'id_user' => $c['id_user'],
                    'id_clando' => $idClando,
                    'id_order' => $idOrder,
                    'wave' => $vague,
                    'score' => $c['score'],
                    'visible_at' => $maintenant->copy()->addSeconds(($vague - 1) * $delaiSecondes),
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            });

            DB::table('course_offer_waves')->insert($lignes->all());
        });
    }

    private static function tailleVague(): int
    {
        return max(1, (int) (Parameter::active()?->distribution_taille_vague ?? 3));
    }

    private static function delaiVagueSecondes(): int
    {
        return max(1, (int) (Parameter::active()?->distribution_delai_vague_s ?? 10));
    }
}
