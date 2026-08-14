<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clando;
use App\Models\User;
use App\Support\AttributionAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carte des courses clando, en accès libre comme le mur des commandes.
 *
 * Même principe que /commandes : un rendu Inertia initial, puis un flux JSON
 * léger interrogé en boucle. La différence tient à ce qu'on suit ici des points
 * qui bougent, pas des lignes de tableau — la carte ne doit donc jamais être
 * reconstruite, seuls les marqueurs se déplacent.
 *
 * D'où vient la position des agents, qui n'est pas évidente :
 *
 *  - pendant une course, l'application pousse la position dans
 *    clando.latAgent/lonAgent toutes les trois secondes. C'est la seule source
 *    réellement temps réel du projet ;
 *  - hors course, l'application pousse la position toutes les trente secondes
 *    tant que l'agent est en service, dans users.actual_lat_position_agent/lon
 *    avec son horodatage. Auparavant ce point n'était écrit qu'au démarrage de
 *    la journée et ne bougeait plus : trois agents « en service » portaient des
 *    positions vieilles de plusieurs semaines. La carte distingue désormais un
 *    relevé récent d'un point dormant.
 *
 * L'endpoint API getLocationAgent existant ne pouvait pas servir : il lit ces
 * colonnes sur la table agents, où l'application n'écrit jamais, et renvoie donc
 * des coordonnées systématiquement nulles.
 */
class ClandoBoardController extends Controller
{
    /** Statuts d'une course encore en vie, donc à afficher sur la carte. */
    private const ACTIFS = ['pending', 'want', 'take', 'process'];

    /**
     * Statuts d'une course que personne n'a encore prise. C'est ce qui déclenche
     * et entretient l'alerte : une demande sans agent doit continuer de sonner.
     */
    private const EN_ATTENTE = ['pending', 'want'];

    /** Libellés des statuts, alignés sur l'écran Clando du tableau de bord. */
    private const STATUTS = [
        'pending' => 'En attente',
        'want' => 'Demandée',
        'take' => 'Prise en charge',
        'process' => 'En cours',
        'Success' => 'Terminée',
        'declin' => 'Refusée',
        'failed' => 'Échec',
    ];

    /*
     | Heure du Cameroun (WAT, UTC+1). Converti à l'affichage seulement : les
     | dates sont stockées en UTC, basculer config('app.timezone') les ferait
     | relire comme des heures de Douala et décalerait tout l'historique.
     */
    private const FUSEAU = 'Africa/Douala';

    /**
     * Nombre de courses terminées conservées sur la carte. Elles servent à
     * comprendre l'activité récente sans noyer les courses en cours.
     */
    private const RECENTES = 15;

    public function index(Request $request): Response
    {
        return Inertia::render('Clando/Board', [
            'initial' => $this->payload($request),
        ]);
    }

    /**
     * Flux interrogé en boucle. Volontairement réduit à ce qui s'affiche : il
     * part toutes les quatre secondes, l'application poussant les positions
     * toutes les trois.
     */
    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->payload($request))
            // La carte peut rester ouverte des heures : un proxy qui mettrait ce
            // flux en cache figerait les agents sur une position périmée, ce qui
            // est pire que pas de carte du tout.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Attribue une course à un agent depuis la carte.
     *
     * Les règles sont celles de takeClandoCommand : l'agent doit exister et
     * pouvoir couvrir la commission, la course ne doit pas être déjà prise. Le
     * détail est dans AttributionAgent, partagé avec la carte des commandes.
     */
    public function assign(Request $request, int $course, AttributionAgent $attribution): JsonResponse
    {
        $valide = $request->validate([
            'id_agent' => ['required', 'integer'],
        ]);

        $cible = Clando::findOrFail($course);

        // Une course se règle sur la commission de l'agent, pas sur son prix :
        // c'est ce que contrôle takeClandoCommand.
        $resultat = $attribution->attribuer($cible, $valide['id_agent'], (float) $cible->commission_agent);

        // La carte à jour accompagne la réponse : l'écran n'a pas à attendre le
        // cycle suivant pour montrer le résultat du clic.
        return response()->json($this->payload($request) + [
            'attribution' => ['ok' => $resultat['ok'], 'message' => $resultat['message']],
        ], $resultat['code'])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $courses = $this->coursesAffichees();

        // Appréciations de la page courante, en une requête plutôt qu'une par
        // course : le mur se rafraîchit en continu.
        $this->appreciations = \App\Models\Note::whereIn('id_clando', $courses->pluck('id'))
            ->get()
            ->keyBy('id_clando');

        return [
            'courses' => $courses->map(fn (Clando $c) => $this->transform($c))->values(),
            'agents' => $this->agents($courses),
            'agents_disponibles' => app(AttributionAgent::class)->agentsDisponibles(),
            'stats' => [
                'actives' => $this->requeteDeBase()->whereIn('status', self::ACTIFS)->count(),
                'en_attente' => $this->requeteDeBase()->whereIn('status', self::EN_ATTENTE)->count(),
                'du_jour' => $this->requeteDeBase()->whereBetween('created_at', $this->bornesDuJour())->count(),
                'ca_jour' => (int) $this->requeteDeBase()
                    ->whereBetween('created_at', $this->bornesDuJour())
                    ->where('status', 'Success')
                    ->sum('price'),
                'agents_actifs' => $this->requeteAgentsActifs()->count(),
            ],
            /*
             | Identifiant le plus élevé, toutes courses confondues et sans filtre
             | de statut : c'est lui qui déclenche l'alerte. Le calculer sur les
             | seules courses affichées raterait une demande arrivée pendant qu'un
             | filtre est actif.
             */
            'latest_id' => (int) $this->requeteDeBase()->max('id'),
            'server_time' => now()->setTimezone(self::FUSEAU)->format('H:i:s'),
            'server_date' => now()->setTimezone(self::FUSEAU)->locale('fr')->translatedFormat('l j F Y'),
        ];
    }

    /**
     * Courses clando, à l'exclusion des courses de coursier.
     *
     * Le critère est delivery_type, comme sur l'écran Clando du tableau de bord :
     * c'est le seul champ qui sépare réellement les deux services. Tout ce qui
     * n'est pas explicitement « delivery » reste visible ici, pour qu'une valeur
     * inattendue ne disparaisse pas silencieusement.
     */
    private function requeteDeBase()
    {
        return Clando::query()->where(function ($q) {
            $q->whereNull('delivery_type')->orWhere('delivery_type', '!=', 'delivery');
        });
    }

    /**
     * Courses en cours, complétées de quelques courses achevées.
     *
     * Les courses actives sont toutes prises, sans pagination : ce sont elles qui
     * portent les points mobiles, en omettre une reviendrait à perdre un agent de
     * vue. Leur nombre est borné par la réalité du terrain, pas par un plafond
     * arbitraire.
     *
     * @return Collection<int, Clando>
     */
    private function coursesAffichees(): Collection
    {
        $relations = ['users:id,name,phone,whatsapp', 'agent.user:id,name,phone,whatsapp'];

        $actives = $this->requeteDeBase()
            ->with($relations)
            ->whereIn('status', self::ACTIFS)
            ->orderByDesc('id')
            ->get();

        $terminees = $this->requeteDeBase()
            ->with($relations)
            ->whereNotIn('status', self::ACTIFS)
            ->orderByDesc('id')
            ->limit(self::RECENTES)
            ->get();

        return $actives->concat($terminees);
    }

    /**
     * Agents en service, avec leur position la plus fraîche disponible.
     *
     * Un agent engagé sur une course apparaît à la position poussée par cette
     * course ; les autres au dernier point connu du démarrage de journée. Le
     * champ « frais » distingue les deux, la carte s'en sert pour ne pas afficher
     * un point immobile depuis des heures comme s'il était suivi en direct.
     *
     * @param  Collection<int, Clando>  $courses
     * @return array<int, array<string, mixed>>
     */
    private function agents(Collection $courses): array
    {
        // Dernière position poussée par une course en cours, par agent.
        $enCourse = [];
        foreach ($courses as $course) {
            if (! in_array($course->status, self::ACTIFS, true) || ! $course->id_agent) {
                continue;
            }

            $lat = $this->nombre($course->latAgent);
            $lon = $this->nombre($course->lonAgent);

            if ($lat === null || $lon === null) {
                continue;
            }

            $enCourse[$course->id_agent] = [
                'lat' => $lat,
                'lon' => $lon,
                'course_ref' => $course->ref,
            ];
        }

        return $this->requeteAgentsActifs()
            ->get(['id', 'name', 'phone', 'whatsapp', 'actual_lat_position_agent', 'actual_lon_position_agent', 'position_updated_at'])
            ->map(function (User $u) use ($enCourse) {
                $suivi = $enCourse[$u->id] ?? null;

                $lat = $suivi['lat'] ?? $this->nombre($u->actual_lat_position_agent);
                $lon = $suivi['lon'] ?? $this->nombre($u->actual_lon_position_agent);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'phone' => $u->phone,
                    'whatsapp' => $u->whatsapp,
                    'lat' => $lat,
                    'lon' => $lon,
                    // Vrai seulement si le point vient d'une course en cours, donc
                    // rafraîchi toutes les trois secondes.
                    /*
                     | Un point est « frais » s'il vient d'une course en cours,
                     | ou s'il a été relevé il y a moins de deux minutes.
                     |
                     | Auparavant seul le premier cas comptait, si bien qu'un
                     | agent en service mais sans course apparaissait comme suivi
                     | alors que sa position datait parfois de plusieurs semaines.
                     */
                    'frais' => $suivi !== null || $this->positionRecente($u->position_updated_at),
                    'position_datee' => $u->position_updated_at
                        ? $u->position_updated_at->setTimezone(self::FUSEAU)->format('d/m H:i')
                        : null,
                    'course_ref' => $suivi['course_ref'] ?? null,
                ];
            })
            // Un agent sans coordonnées n'a rien à faire sur une carte : il n'a
            // pas encore démarré sa journée ou la position n'a pas été transmise.
            ->filter(fn (array $a) => $a['lat'] !== null && $a['lon'] !== null)
            ->values()
            ->all();
    }

    /** Agents ayant démarré leur journée. */
    /**
     * Une position relevée il y a moins de deux minutes vaut du direct.
     *
     * L'application pousse toutes les trente secondes tant que l'agent est en
     * service : deux minutes laissent passer trois envois manqués — un tunnel,
     * un changement de réseau — sans faire clignoter le marqueur.
     */
    private function positionRecente($horodatage): bool
    {
        return $horodatage !== null && $horodatage->greaterThan(now()->subMinutes(2));
    }

    private function requeteAgentsActifs()
    {
        return User::query()->where('role', 'agent')->where('in_activity', 1);
    }

    /**
     * Début et fin de la journée camerounaise, exprimés en UTC pour interroger la
     * base. Sans cette conversion, les compteurs « du jour » basculeraient à
     * minuit UTC, soit une heure du matin à Douala.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function bornesDuJour(): array
    {
        $debut = now()->setTimezone(self::FUSEAU)->startOfDay();

        return [
            $debut->copy()->utc(),
            $debut->copy()->endOfDay()->utc(),
        ];
    }

    /**
     * Coordonnée exploitable, ou null.
     *
     * Les colonnes de position de clando et users sont des double nullables, à la
     * différence de celles de locations qui sont des varchar. Restent donc deux
     * cas à écarter : null, et le zéro posé quand la position n'a pas été relevée.
     * Un zéro tombe au large du golfe de Guinée : affiché tel quel, il envoie
     * l'agent à mille kilomètres de Douala et fait dézoomer la carte sur
     * l'Atlantique.
     */
    private function nombre($valeur): ?float
    {
        if ($valeur === null || $valeur === '' || ! is_numeric($valeur)) {
            return null;
        }

        $nombre = (float) $valeur;

        return $nombre === 0.0 ? null : $nombre;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Appréciations de la page courante, indexées par course.
     *
     * @var \Illuminate\Support\Collection|null
     */
    private $appreciations = null;

    private function transform(Clando $course): array
    {
        $depart = $this->point($course->latMyPosition, $course->lonMyPosition);
        $arrivee = $this->point($course->latDestination, $course->lonDestination);
        $agent = $this->point($course->latAgent, $course->lonAgent);

        return [
            'id' => $course->id,
            'ref' => $course->ref,
            'status' => $course->status,
            'status_label' => self::STATUTS[$course->status] ?? $course->status,
            'active' => in_array($course->status, self::ACTIFS, true),
            'en_attente' => in_array($course->status, self::EN_ATTENTE, true),
            // Une course déjà prise ne doit pas proposer de bouton d'attribution :
            // l'écran s'appuie là-dessus, et le serveur le revérifie sous verrou.
            'attribuable' => $course->id_agent === null,
            'price' => (int) $course->price,
            // Ce que le client a pensé de la course, une fois terminée.
            'appreciation' => $this->appreciation($course->id),
            'distance' => $course->distance,
            'times' => $course->times,
            'vehicule' => $course->vehicule,
            'matricule' => $course->matricule_vehicule,
            'destination' => $course->destinationName,
            'depart' => $depart,
            'arrivee' => $arrivee,
            // Position de l'agent portée par la course : c'est elle qui bouge.
            'agent_position' => $agent,
            'client' => $course->users ? [
                'name' => $course->users->name,
                'phone' => $course->users->phone,
                'whatsapp' => $course->users->whatsapp,
            ] : null,
            'agent' => $course->agent?->user ? [
                'id' => $course->agent->user->id,
                'name' => $course->agent->user->name,
                'phone' => $course->agent->user->phone,
                'whatsapp' => $course->agent->user->whatsapp,
            ] : null,
            'created_at' => $course->created_at?->toIso8601String(),
            'created_label' => $course->created_at?->setTimezone(self::FUSEAU)->format('H:i'),
            'created_day' => $this->libelleJour($course->created_at),
        ];
    }

    /**
     * Couple de coordonnées exploitable, ou null si l'une des deux manque.
     *
     * @return array{lat: float, lon: float}|null
     */
    /**
     * @return array<string, mixed>|null
     */
    private function appreciation(int $idCourse): ?array
    {
        $note = $this->appreciations?->get($idCourse);

        if (! $note) {
            return null;
        }

        return [
            'note' => $note->note,
            'libelle' => \App\Support\NotationAgent::LIBELLES[$note->note] ?? $note->note,
            'emoji' => \App\Support\NotationAgent::EMOJIS[$note->note] ?? '',
            'commentaire' => $note->comment,
        ];
    }

    private function point($lat, $lon): ?array
    {
        $lat = $this->nombre($lat);
        $lon = $this->nombre($lon);

        if ($lat === null || $lon === null) {
            return null;
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    /**
     * Jour d'une course en heure du Cameroun, en repères lisibles d'un coup d'œil.
     */
    private function libelleJour(?\Illuminate\Support\Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        $local = $date->copy()->setTimezone(self::FUSEAU);
        $aujourdhui = now()->setTimezone(self::FUSEAU)->startOfDay();

        return match (true) {
            $local->isSameDay($aujourdhui) => "Aujourd'hui",
            $local->isSameDay($aujourdhui->copy()->subDay()) => 'Hier',
            default => $local->format('d/m/Y'),
        };
    }
}
