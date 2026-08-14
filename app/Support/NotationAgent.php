<?php

namespace App\Support;

use App\Models\Note;
use App\Models\Parameter;
use Illuminate\Support\Collection;

/**
 * Ce que valent les appréciations laissées par les clients.
 *
 * Le calcul était enfermé dans NoteController, avec un barème écrit en dur, et
 * n'existait donc qu'à un seul endroit : l'application agent. Le tableau de
 * bord ne pouvait afficher aucun score, les murs publics non plus, et personne
 * ne pouvait comparer deux agents.
 *
 * Tout passe désormais par ici, pour que le score lu par l'agent sur son
 * téléphone soit au caractère près celui que l'administrateur voit à l'écran.
 * Deux calculs séparés finiraient par diverger, et le premier à s'en plaindre
 * serait l'agent dont la prime en dépend.
 */
class NotationAgent
{
    /**
     * Barème en vigueur.
     *
     * Lu une seule fois par requête : appelé pour chaque agent d'une liste, il
     * relirait sinon la configuration à chaque ligne.
     *
     * @var array<string, float>|null
     */
    private static ?array $bareme = null;

    /**
     * @return array<string, float>
     */
    public static function bareme(): array
    {
        if (self::$bareme !== null) {
            return self::$bareme;
        }

        $active = Parameter::active();

        return self::$bareme = $active
            ? $active->pointsDeNotation()
            : Parameter::POINTS_PAR_DEFAUT;
    }

    /** À n'appeler que dans les tests, quand la configuration change en cours de route. */
    public static function oublierBareme(): void
    {
        self::$bareme = null;
    }

    /**
     * Bilan de notation d'un agent.
     *
     * @return array<string, mixed>
     */
    public static function pourAgent(int $idAgent): array
    {
        $comptes = Note::where('id_agent', $idAgent)
            ->selectRaw('note, COUNT(*) as nombre')
            ->groupBy('note')
            ->pluck('nombre', 'note')
            ->toArray();

        return self::bilan($comptes);
    }

    /**
     * Bilan de notation de plusieurs agents, en une seule requête.
     *
     * Appeler pourAgent() dans une boucle sur la liste des agents produirait
     * autant de requêtes que d'agents ; sur l'écran qui les classe, c'est la
     * différence entre une page et une attente.
     *
     * @param  iterable<int>  $idsAgents
     * @return array<int, array<string, mixed>>
     */
    public static function pourAgents(iterable $idsAgents): array
    {
        $ids = collect($idsAgents)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $lignes = Note::whereIn('id_agent', $ids)
            ->selectRaw('id_agent, note, COUNT(*) as nombre')
            ->groupBy('id_agent', 'note')
            ->get();

        $bilans = [];

        foreach ($ids as $id) {
            $comptes = $lignes->where('id_agent', $id)->pluck('nombre', 'note')->toArray();
            $bilans[(int) $id] = self::bilan($comptes);
        }

        return $bilans;
    }

    /**
     * Traduit un décompte d'appréciations en score.
     *
     * @param  array<string, int>  $comptes
     * @return array<string, mixed>
     */
    public static function bilan(array $comptes): array
    {
        $bareme = self::bareme();
        $detail = [];
        $total = 0.0;
        $nombre = 0;

        foreach (Parameter::APPRECIATIONS as $appreciation) {
            $combien = (int) ($comptes[$appreciation] ?? 0);
            $detail[$appreciation] = $combien;
            $total += $combien * $bareme[$appreciation];
            $nombre += $combien;
        }

        return [
            'compte' => $detail,
            'nombre' => $nombre,
            'total' => round($total, 2),
            /*
             | La moyenne, et non le seul total.
             |
             | Un total récompense l'ancienneté autant que la qualité : l'agent
             | qui a fait mille courses correctes devance toujours celui qui en
             | a fait dix excellentes. Pour classer des agents entre eux, c'est
             | la moyenne qui a un sens.
             */
            'moyenne' => $nombre === 0 ? null : round($total / $nombre, 2),
            /*
             | Note sur cinq, pour l'affichage.
             |
             | Le barème est signé et sans borne fixe : tel quel, il ne se met
             | pas en étoiles. On le ramène linéairement de [pire, meilleur] vers
             | [0, 5] — un agent noté uniquement « moyen » retombe ainsi au
             | milieu de l'échelle, et non au bas.
             */
            'sur_cinq' => $nombre === 0 ? null : self::surCinq($total / $nombre),
            'bareme' => $bareme,
        ];
    }

    /**
     * Ramène une moyenne de points sur une échelle de 0 à 5.
     */
    private static function surCinq(float $moyenne): float
    {
        $bareme = self::bareme();
        $pire = min($bareme);
        $meilleur = max($bareme);

        // Barème plat — toutes les appréciations valent pareil : aucune échelle
        // n'a de sens, on renvoie le milieu plutôt qu'une division par zéro.
        if ($meilleur - $pire <= 0) {
            return 2.5;
        }

        $rapporte = ($moyenne - $pire) / ($meilleur - $pire) * 5;

        return round(max(0, min(5, $rapporte)), 1);
    }

    /**
     * Les appréciations laissées à un agent, les plus récentes d'abord.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function appreciations(int $idAgent, ?int $limite = null): Collection
    {
        $requete = Note::with(['client:id,name,phone'])
            ->where('id_agent', $idAgent)
            ->orderByDesc('id');

        if ($limite) {
            $requete->limit($limite);
        }

        return $requete->get()->map(fn (Note $note) => self::detailler($note));
    }

    /**
     * Mise en forme d'une appréciation, commune à tous les écrans.
     *
     * @return array<string, mixed>
     */
    public static function detailler(Note $note): array
    {
        $bareme = self::bareme();

        return [
            'id' => $note->id,
            'note' => $note->note,
            'libelle' => self::LIBELLES[$note->note] ?? $note->note,
            'emoji' => self::EMOJIS[$note->note] ?? '',
            'points' => $bareme[$note->note] ?? 0,
            'commentaire' => $note->comment,
            'id_order' => $note->id_order,
            'id_clando' => $note->id_clando,
            // Le client sait ce qu'il a noté ; l'administrateur qui lit un
            // commentaire sévère a besoin de savoir de quelle prestation il
            // parle pour pouvoir vérifier.
            'prestation' => $note->id_clando ? 'clando' : 'commande',
            'client' => $note->client?->name,
            'date' => $note->created_at?->toIso8601String(),
        ];
    }

    public const LIBELLES = [
        'verybad' => 'Très mauvais',
        'bad' => 'Mauvais',
        'average' => 'Moyen',
        'good' => 'Bien',
        'excellent' => 'Excellent',
    ];

    public const EMOJIS = [
        'verybad' => '😡',
        'bad' => '🙁',
        'average' => '😐',
        'good' => '🙂',
        'excellent' => '🤩',
    ];
}
