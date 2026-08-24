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
            'bareme' => $bareme,
        ];
    }

    /** Équivalent 1-5 étoiles de chaque appréciation — une pure fonction de l'enum, pas une donnée dupliquée. */
    public const ETOILES = [
        'verybad' => 1,
        'bad' => 2,
        'average' => 3,
        'good' => 4,
        'excellent' => 5,
    ];

    /**
     * Décroissance du poids d'une note selon son rang (1 = la plus récente).
     *
     * Fixée en dur plutôt qu'exposée en réglage : contrairement à la fenêtre
     * (N) et à la crédibilité (C), que l'utilisateur a explicitement demandé
     * de pouvoir régler, personne n'a demandé à régler la vitesse de
     * décroissance elle-même — c'est un détail d'implémentation de « pondéré
     * par la récence », pas un paramètre métier.
     *
     * 0.985^149 ≈ 0.11 : la 150e note la plus ancienne de la fenêtre compte
     * encore pour environ un dixième d'une note fraîche, une traîne réelle
     * mais pas négligeable — cohérent avec « pondéré par la récence » plutôt
     * que « seules les 10 dernières comptent ».
     */
    private const DECROISSANCE = 0.985;

    /**
     * Note affichée au client/à l'agent : moyenne pondérée par la récence,
     * puis stabilisée statistiquement (moyenne bayésienne) pour qu'un agent
     * tout juste arrivé avec une seule excellente note n'affiche pas
     * immédiatement 5/5.
     *
     * Distincte de bilan()/total/moyenne : celles-ci restent le score par
     * points (barème administrable, récompense le volume), utilisé ailleurs
     * (classement en cas d'égalité, stat « Total » côté agent). note_affichee
     * ne sert qu'à l'affichage — jamais à l'attribution des courses, qui
     * passe uniquement par DistributionScore/IndicateursAgent.
     */
    public static function noteAffichee(int $idAgent): ?float
    {
        return self::noteAfficheePourAgents([$idAgent])[$idAgent] ?? null;
    }

    /**
     * Version batchée, même discipline anti-N+1 que pourAgents() : une
     * requête pour la moyenne globale (partagée par tous les agents demandés
     * dans ce même appel), une requête pour toutes leurs notes.
     *
     * @param  iterable<int>  $idsAgents
     * @return array<int, float|null>
     */
    public static function noteAfficheePourAgents(iterable $idsAgents): array
    {
        $ids = collect($idsAgents)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $fenetre = self::fenetreRecente();
        $credibilite = self::credibiliteC();
        $moyenneGlobale = self::moyenneGlobale();

        $lignes = Note::whereIn('id_agent', $ids)
            ->orderByDesc('created_at')
            ->get(['id_agent', 'note', 'created_at']);

        $resultat = [];

        foreach ($ids as $id) {
            $notesAgent = $lignes->where('id_agent', $id)->take($fenetre)->values();

            if ($notesAgent->isEmpty()) {
                $resultat[(int) $id] = null;

                continue;
            }

            $poidsTotal = 0.0;
            $sommePonderee = 0.0;

            foreach ($notesAgent as $rang => $note) {
                $poids = self::DECROISSANCE ** $rang;
                $poidsTotal += $poids;
                $sommePonderee += $poids * (self::ETOILES[$note->note] ?? 3);
            }

            $moyenneAgent = $sommePonderee / $poidsTotal;
            $nAgent = $notesAgent->count();

            $stabilisee = ($credibilite * $moyenneGlobale + $nAgent * $moyenneAgent) / ($credibilite + $nAgent);

            $resultat[(int) $id] = round(max(1, min(5, $stabilisee)), 1);
        }

        return $resultat;
    }

    /**
     * Moyenne (1-5) de toutes les notes de la plateforme — la référence de
     * la stabilisation bayésienne, et aussi la "note moyenne toutes
     * prestations confondues" affichée au tableau de bord.
     */
    public static function moyenneGlobale(): float
    {
        $comptes = Note::selectRaw('note, COUNT(*) as nombre')->groupBy('note')->pluck('nombre', 'note');

        $poidsTotal = 0;
        $somme = 0.0;

        foreach ($comptes as $appreciation => $nombre) {
            $somme += ($nombre) * (self::ETOILES[$appreciation] ?? 3);
            $poidsTotal += $nombre;
        }

        // Aucune note nulle part sur la plateforme : 3/5 (le milieu de
        // l'échelle) sert de référence neutre plutôt qu'une division par zéro.
        return $poidsTotal === 0 ? 3.0 : $somme / $poidsTotal;
    }

    private static function fenetreRecente(): int
    {
        $active = Parameter::active();

        return $active?->note_fenetre_recente ?? 150;
    }

    private static function credibiliteC(): int
    {
        $active = Parameter::active();

        return $active?->note_credibilite_c ?? 20;
    }

    /**
     * Raisons proposées pour une appréciation basse (verybad/bad/average) —
     * calqué sur AnnulationDeCommande::MOTIFS_CLIENT (liste fermée + « Autre
     * raison » en texte libre). Jamais demandé/stocké pour une bonne
     * appréciation (voir NoteController::makeNote).
     */
    public const MOTIFS_NOTATION = [
        'Retard important',
        'Agent impoli ou irrespectueux',
        'Colis/commande endommagé',
        'Erreur sur la commande',
        'Conduite dangereuse',
        'Autre raison',
    ];

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
            'reasons' => $note->reasons ?: [],
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
