import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import DemandeDeMotif from '@/Components/Board/DemandeDeMotif';
import { Head } from '@inertiajs/react';
import L from 'leaflet';
import {
    ChoixAgent,
    Compteur,
    echapper,
    formatMontant,
    iconeAgent,
    iconePoint,
    useAlerteSonore,
    useCarteLeaflet,
    useDebounce,
    useSynchronisation,
    deplacerEnDouceur,
} from '../Shared/carte';

/*
 * Carte des courses clando, en accès libre comme le mur des commandes.
 *
 * Le socle Leaflet, l'alerte sonore et le choix d'agent sont partagés avec la
 * carte des livraisons (voir Shared/carte). Ne reste ici que ce qui est propre
 * aux courses : leurs statuts, leurs points, et la règle d'attribution.
 */

// L'application pousse les positions toutes les 3 s : interroger plus vite ne
// donnerait rien de neuf, plus lentement ferait sauter les marqueurs.
const INTERVALLE_MS = 4000;
const RAPPEL_SONORE_MS = 20000;
// Depuis combien de temps ce point a-t-il été relevé.
const ageLisible = (secondes) => {
    if (secondes === null || secondes === undefined) return 'aucun relevé';
    if (secondes < 15) return "à l'instant";
    if (secondes < 90) return `il y a ${Math.round(secondes)} s`;
    if (secondes < 5400) return `il y a ${Math.round(secondes / 60)} min`;

    return `il y a ${Math.round(secondes / 3600)} h`;
};

const CLE_SON = 'carte-clando.son-active';

const STATUTS = {
    pending: { couleur: '#f59e0b', classe: 'bg-amber-100 text-amber-800 ring-amber-300' },
    want: { couleur: '#ef4444', classe: 'bg-red-100 text-red-800 ring-red-300' },
    take: { couleur: '#6366f1', classe: 'bg-indigo-100 text-indigo-800 ring-indigo-300' },
    process: { couleur: '#0ea5e9', classe: 'bg-sky-100 text-sky-800 ring-sky-300' },
    Success: { couleur: '#10b981', classe: 'bg-emerald-100 text-emerald-800 ring-emerald-300' },
    declin: { couleur: '#94a3b8', classe: 'bg-slate-100 text-slate-700 ring-slate-300' },
    failed: { couleur: '#dc2626', classe: 'bg-red-100 text-red-800 ring-red-300' },
};

const couleurStatut = (statut) => STATUTS[statut]?.couleur ?? '#64748b';

const jetonCsrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function Board({ initial }) {
    const [donnees, setDonnees] = useState(initial);
    const [enLigne, setEnLigne] = useState(true);
    const [heure, setHeure] = useState(new Date());
    const [recherche, setRecherche] = useState('');
    const [filtreStatut, setFiltreStatut] = useState('actives');
    const [selection, setSelection] = useState(null);
    const [nouvelles, setNouvelles] = useState(new Set());
    const [couches, setCouches] = useState({ agents: true, clients: true, destinations: true, trajets: true });
    const [attribution, setAttribution] = useState(null);
    const [enCoursAttribution, setEnCoursAttribution] = useState(false);
    const [retour, setRetour] = useState(null);

    const dernierIdRef = useRef(initial.latest_id ?? 0);
    const conteneurRef = useRef(null);
    const marqueursAgents = useRef(new Map());
    const marqueursClients = useRef(new Map());
    const marqueursDestinations = useRef(new Map());
    const tracesRef = useRef(new Map());
    const cadrageInitialRef = useRef(false);

    const { audioRef, sonActif, silence, jouer, activer, couper, leverSilence } = useAlerteSonore(CLE_SON);

    const carteRef = useCarteLeaflet(conteneurRef, { surDeplacement: () => setSelection(null) });
    const synchroniser = useSynchronisation(carteRef);

    useEffect(() => {
        const timer = setInterval(() => setHeure(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    const courses = donnees.courses ?? [];
    const agents = donnees.agents ?? [];
    const agentsDisponibles = donnees.agents_disponibles ?? [];
    const stats = donnees.stats ?? {};

    const nbEnAttente = useMemo(() => courses.filter((c) => c.en_attente).length, [courses]);

    useEffect(() => {
        if (nbEnAttente === 0) return undefined;

        const timer = setInterval(jouer, RAPPEL_SONORE_MS);
        return () => clearInterval(timer);
    }, [jouer, nbEnAttente]);

    useEffect(() => {
        if (silence && nbEnAttente === 0) leverSilence();
    }, [nbEnAttente, silence, leverSilence]);

    /* ---------------------------------------------------------------- flux */

    const appliquer = useCallback(
        (charge) => {
            setDonnees(charge);

            const idMax = charge.latest_id ?? 0;

            if (idMax > dernierIdRef.current) {
                const fraiches = (charge.courses ?? [])
                    .filter((c) => c.id > dernierIdRef.current)
                    .map((c) => c.id);

                dernierIdRef.current = idMax;
                setNouvelles((precedent) => new Set([...precedent, ...fraiches]));

                // Une nouvelle demande lève le silence : un « couper le son »
                // cliqué plus tôt ne doit pas masquer la suivante.
                leverSilence();
                jouer();
            }
        },
        [jouer, leverSilence],
    );

    useEffect(() => {
        let annule = false;

        const recuperer = async () => {
            try {
                const reponse = await fetch('/clando/flux', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!reponse.ok) throw new Error(String(reponse.status));

                const charge = await reponse.json();
                if (annule) return;

                setEnLigne(true);
                appliquer(charge);
            } catch {
                if (!annule) setEnLigne(false);
            }
        };

        const timer = setInterval(recuperer, INTERVALLE_MS);
        return () => {
            annule = true;
            clearInterval(timer);
        };
    }, [appliquer]);

    /* --------------------------------------------------------- attribution */

    const attribuer = useCallback(
        async (courseId, agent) => {
            setEnCoursAttribution(true);
            setRetour(null);

            try {
                const reponse = await fetch(`/clando/${courseId}/agent`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': jetonCsrf(),
                    },
                    body: JSON.stringify({ id_agent: agent.id_user }),
                });

                const charge = await reponse.json();

                // La réponse porte déjà la carte à jour, succès ou échec : on
                // l'applique sans attendre le cycle suivant, sinon le clic semble
                // ne rien faire pendant quatre secondes.
                if (charge.courses) appliquer(charge);

                setRetour({
                    ok: reponse.ok && charge.attribution?.ok,
                    message: charge.attribution?.message ?? 'Attribution impossible.',
                });

                if (reponse.ok && charge.attribution?.ok) setAttribution(null);
            } catch {
                setRetour({ ok: false, message: 'Le serveur n\'a pas répondu.' });
            } finally {
                setEnCoursAttribution(false);
            }
        },
        [appliquer],
    );

    /*
     * Annulation d'une course, motif obligatoire.
     *
     * La carte savait attribuer, jamais annuler : une demande sans suite y
     * restait « active » indéfiniment, gonflant le compteur des courses en cours
     * et masquant les vraies. Il fallait passer par la base.
     */
    const [annulationDemandee, setAnnulationDemandee] = useState(null);
    const [enCoursAnnulation, setEnCoursAnnulation] = useState(false);

    const annuler = useCallback(
        async (courseId, motif) => {
            setEnCoursAnnulation(true);
            setRetour(null);

            try {
                const reponse = await fetch(`/clando/${courseId}/annulation`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': jetonCsrf(),
                    },
                    body: JSON.stringify({ reason: motif }),
                });

                const charge = await reponse.json();

                if (charge.courses) appliquer(charge);

                setRetour({
                    ok: reponse.ok && charge.annulation?.ok,
                    message: charge.annulation?.message
                        ?? charge.message
                        ?? "L'annulation n'a pas abouti.",
                });
            } catch {
                setRetour({ ok: false, message: 'Le serveur n\'a pas répondu.' });
            } finally {
                setEnCoursAnnulation(false);
                setAnnulationDemandee(null);
            }
        },
        [appliquer],
    );

    // Le message de retour s'efface seul : il documente un geste, il n'a pas à
    // rester en travers de l'écran.
    useEffect(() => {
        if (!retour) return undefined;
        const timer = setTimeout(() => setRetour(null), 6000);
        return () => clearTimeout(timer);
    }, [retour]);

    /* -------------------------------------------------------------- filtres */

    const termeRetarde = useDebounce(recherche.trim().toLowerCase(), 150);

    const coursesFiltrees = useMemo(() => {
        return courses.filter((c) => {
            if (filtreStatut === 'actives' && !c.active) return false;
            if (filtreStatut === 'attente' && !c.en_attente) return false;
            if (filtreStatut === 'sans_agent' && !c.attribuable) return false;
            if (!['actives', 'attente', 'sans_agent', 'toutes'].includes(filtreStatut) && c.status !== filtreStatut)
                return false;

            if (!termeRetarde) return true;

            return [c.ref, c.destination, c.client?.name, c.agent?.name]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(termeRetarde));
        });
    }, [courses, filtreStatut, termeRetarde]);

    /* ------------------------------------------------------------ marqueurs */

    useEffect(() => {
        const elements = couches.agents
            ? agents.filter((a) => a.lat !== null && a.lon !== null).map((a) => ({ ...a, cle: `agent-${a.id}` }))
            : [];

        const contenu = (a) =>
            `<div class="text-sm">
                <div class="font-semibold">${echapper(a.name)}</div>
                <div class="text-slate-500">${echapper(a.phone ?? '')}</div>
                <div class="mt-1">${a.frais ? 'Suivi en direct' : 'Position dormante'} · ${echapper(ageLisible(a.position_age_s))}${a.frais && a.course_ref ? ' · course ' + echapper(a.course_ref) : ''}</div>
            </div>`;

        synchroniser(
            marqueursAgents,
            elements,
            (a) => L.marker([a.lat, a.lon], { icon: iconeAgent(a.frais), zIndexOffset: 500 }).bindPopup(contenu(a)),
            (marqueur, a) => {
                // Le marqueur rejoint sa nouvelle position au lieu d'y sauter :
                // c'est ce qui rend le déplacement lisible entre deux relevés.
                deplacerEnDouceur(marqueur, [a.lat, a.lon]);
                marqueur.setIcon(iconeAgent(a.frais));
                marqueur.setPopupContent(contenu(a));
            },
        );
    }, [agents, couches.agents, synchroniser]);

    useEffect(() => {
        const elements = couches.clients
            ? coursesFiltrees.filter((c) => c.depart).map((c) => ({ ...c, cle: `client-${c.id}` }))
            : [];

        const contenu = (c) =>
            `<div class="text-sm">
                <div class="font-semibold">${echapper(c.client?.name ?? 'Client')}</div>
                <div class="text-slate-500">${echapper(c.client?.phone ?? '')}</div>
                <div class="mt-1">${echapper(c.ref)} · ${echapper(c.status_label)}</div>
                <div>${formatMontant(c.price)} FCFA${c.destination ? ' · ' + echapper(c.destination) : ''}</div>
            </div>`;

        synchroniser(
            marqueursClients,
            elements,
            (c) =>
                L.marker([c.depart.lat, c.depart.lon], {
                    icon: iconePoint(couleurStatut(c.status), 'client', c.en_attente),
                    zIndexOffset: 400,
                })
                    .bindPopup(contenu(c))
                    .on('click', () => setSelection(c.id)),
            (marqueur, c) => {
                marqueur.setLatLng([c.depart.lat, c.depart.lon]);
                marqueur.setIcon(iconePoint(couleurStatut(c.status), 'client', c.en_attente));
                marqueur.setPopupContent(contenu(c));
            },
        );
    }, [coursesFiltrees, couches.clients, synchroniser]);

    useEffect(() => {
        const elements = couches.destinations
            ? coursesFiltrees.filter((c) => c.arrivee).map((c) => ({ ...c, cle: `dest-${c.id}` }))
            : [];

        synchroniser(
            marqueursDestinations,
            elements,
            (c) =>
                L.marker([c.arrivee.lat, c.arrivee.lon], {
                    icon: iconePoint(couleurStatut(c.status), 'lieu'),
                    zIndexOffset: 300,
                }).bindPopup(
                    `<div class="text-sm"><div class="font-semibold">Destination</div><div>${echapper(c.destination ?? '—')}</div><div class="text-slate-500">${echapper(c.ref)}</div></div>`,
                ),
            (marqueur, c) => {
                marqueur.setLatLng([c.arrivee.lat, c.arrivee.lon]);
                marqueur.setIcon(iconePoint(couleurStatut(c.status), 'lieu'));
            },
        );
    }, [coursesFiltrees, couches.destinations, synchroniser]);

    useEffect(() => {
        const elements = couches.trajets
            ? coursesFiltrees.filter((c) => c.depart && c.arrivee).map((c) => ({ ...c, cle: `trace-${c.id}` }))
            : [];

        synchroniser(
            tracesRef,
            elements,
            (c) =>
                L.polyline(
                    [
                        [c.depart.lat, c.depart.lon],
                        [c.arrivee.lat, c.arrivee.lon],
                    ],
                    { color: couleurStatut(c.status), weight: 3, opacity: 0.6, dashArray: '6 8' },
                ),
            (trace, c) => {
                trace.setLatLngs([
                    [c.depart.lat, c.depart.lon],
                    [c.arrivee.lat, c.arrivee.lon],
                ]);
                trace.setStyle({ color: couleurStatut(c.status) });
            },
        );
    }, [coursesFiltrees, couches.trajets, synchroniser]);

    /* --------------------------------------------------------------- cadrage */

    const recadrer = useCallback(() => {
        const carte = carteRef.current;
        if (!carte) return;

        const points = [];
        agents.forEach((a) => a.lat !== null && a.lon !== null && points.push([a.lat, a.lon]));
        coursesFiltrees.forEach((c) => {
            if (c.depart) points.push([c.depart.lat, c.depart.lon]);
            if (c.arrivee) points.push([c.arrivee.lat, c.arrivee.lon]);
        });

        if (points.length === 0) return;

        carte.fitBounds(L.latLngBounds(points), { padding: [60, 60], maxZoom: 15 });
    }, [agents, carteRef, coursesFiltrees]);

    useEffect(() => {
        if (cadrageInitialRef.current) return;
        if (agents.length === 0 && coursesFiltrees.length === 0) return;

        cadrageInitialRef.current = true;
        recadrer();
    }, [agents.length, coursesFiltrees.length, recadrer]);

    useEffect(() => {
        const carte = carteRef.current;
        if (!carte || selection === null) return;

        const course = courses.find((c) => c.id === selection);
        if (!course) return;

        const points = [];
        if (course.depart) points.push([course.depart.lat, course.depart.lon]);
        if (course.arrivee) points.push([course.arrivee.lat, course.arrivee.lon]);
        if (course.agent_position) points.push([course.agent_position.lat, course.agent_position.lon]);

        if (points.length === 0) return;

        carte.fitBounds(L.latLngBounds(points), { padding: [80, 80], maxZoom: 16 });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection]);

    const filtres = [
        { cle: 'actives', libelle: 'En cours' },
        { cle: 'sans_agent', libelle: 'Sans agent' },
        { cle: 'toutes', libelle: 'Toutes' },
    ];

    return (
        <>
            <Head title="Carte clando" />

            <div className="flex h-screen flex-col overflow-hidden bg-slate-100">
                <header className="flex flex-wrap items-center gap-4 border-b border-slate-200 bg-white px-5 py-3 shadow-sm">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-900">Carte clando</h1>
                        <p className="text-xs text-slate-500">
                            {donnees.server_date} · {donnees.server_time}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Compteur libelle="Courses actives" valeur={stats.actives ?? 0} ton="sky" />
                        <Compteur libelle="Sans agent" valeur={stats.en_attente ?? 0} ton="red" />
                        <Compteur libelle="Agents en service" valeur={stats.agents_actifs ?? 0} ton="emerald" />
                        <Compteur libelle="Du jour" valeur={stats.du_jour ?? 0} />
                        <Compteur libelle="CA du jour" valeur={`${formatMontant(stats.ca_jour)} F`} />
                    </div>

                    <div className="ml-auto flex items-center gap-2">
                        <a
                            href="/commandes/carte"
                            className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                        >
                            Carte livraisons
                        </a>
                        <a
                            href="/commandes"
                            className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                        >
                            Mur des commandes
                        </a>

                        <span
                            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ${
                                enLigne
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : 'bg-red-50 text-red-700 ring-red-200'
                            }`}
                        >
                            <span className={`h-2 w-2 rounded-full ${enLigne ? 'bg-emerald-500' : 'bg-red-500'}`} />
                            {enLigne ? 'En direct' : 'Contact perdu'}
                        </span>

                        <span className="text-sm font-semibold tabular-nums text-slate-700">
                            {heure.toLocaleTimeString('fr-FR')}
                        </span>

                        {!sonActif && (
                            <button
                                type="button"
                                onClick={activer}
                                className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700"
                            >
                                Activer le son
                            </button>
                        )}

                        {sonActif && nbEnAttente > 0 && !silence && (
                            <button
                                type="button"
                                onClick={couper}
                                className="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600"
                            >
                                Couper le son
                            </button>
                        )}
                    </div>
                </header>

                <div className="flex min-h-0 flex-1">
                    <aside className="flex w-80 shrink-0 flex-col border-r border-slate-200 bg-white">
                        <div className="space-y-3 border-b border-slate-200 p-4">
                            <input
                                type="search"
                                value={recherche}
                                onChange={(e) => setRecherche(e.target.value)}
                                placeholder="Référence, client, agent, destination"
                                className="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                            />

                            <div className="flex gap-1.5">
                                {filtres.map((f) => (
                                    <button
                                        key={f.cle}
                                        type="button"
                                        onClick={() => setFiltreStatut(f.cle)}
                                        className={`flex-1 rounded-lg px-2 py-1.5 text-xs font-semibold transition ${
                                            filtreStatut === f.cle
                                                ? 'bg-slate-900 text-white'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                    >
                                        {f.libelle}
                                    </button>
                                ))}
                            </div>

                            <div className="space-y-1.5">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                    Calques
                                </p>
                                {[
                                    { cle: 'agents', libelle: 'Agents' },
                                    { cle: 'clients', libelle: 'Clients' },
                                    { cle: 'destinations', libelle: 'Destinations' },
                                    { cle: 'trajets', libelle: 'Trajets' },
                                ].map((c) => (
                                    <label
                                        key={c.cle}
                                        className="flex cursor-pointer items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={couches[c.cle]}
                                            onChange={() => setCouches((v) => ({ ...v, [c.cle]: !v[c.cle] }))}
                                            className="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                        />
                                        {c.libelle}
                                    </label>
                                ))}
                            </div>

                            <button
                                type="button"
                                onClick={() => {
                                    setSelection(null);
                                    recadrer();
                                }}
                                className="w-full rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                            >
                                Recadrer sur tout
                            </button>
                        </div>

                        {retour && (
                            <div
                                className={`px-4 py-2 text-xs font-semibold ${
                                    retour.ok ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'
                                }`}
                            >
                                {retour.message}
                            </div>
                        )}

                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {coursesFiltrees.length === 0 ? (
                                <p className="p-6 text-center text-sm text-slate-400">Aucune course</p>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {coursesFiltrees.map((c) => (
                                        <li key={c.id} className={nouvelles.has(c.id) ? 'bg-amber-50' : ''}>
                                            <button
                                                type="button"
                                                onClick={() => setSelection(c.id === selection ? null : c.id)}
                                                className={`w-full px-4 py-3 text-left transition ${
                                                    selection === c.id ? 'bg-slate-100' : 'hover:bg-slate-50'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="truncate text-sm font-semibold text-slate-900">
                                                        {c.client?.name ?? 'Client'}
                                                    </span>
                                                    <span
                                                        className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ${
                                                            STATUTS[c.status]?.classe ??
                                                            'bg-slate-100 text-slate-700 ring-slate-300'
                                                        }`}
                                                    >
                                                        {c.status_label}
                                                    </span>
                                                </div>
                                                <p className="mt-0.5 truncate text-xs text-slate-500">
                                                    {c.destination || 'Destination non renseignée'}
                                                </p>
                                                <div className="mt-1 flex items-center justify-between text-xs text-slate-400">
                                                    <span>{c.agent?.name ? `Agent : ${c.agent.name}` : 'Aucun agent'}</span>
                                                    <span className="tabular-nums">
                                                        {formatMontant(c.price)} F · {c.created_label}
                                                    </span>
                                                </div>

                                                {/*
                                                  * Appréciation du client, une fois la course terminée.
                                                  * Elle dormait en base sans être lisible nulle part :
                                                  * une course mal vécue ne se voyait qu'au rappel du client.
                                                  */}
                                                {c.appreciation && (
                                                    <div className="mt-1.5 rounded-lg bg-amber-50 px-2 py-1 text-left ring-1 ring-amber-200">
                                                        <p className="text-xs font-semibold text-amber-800">
                                                            <span className="mr-1">{c.appreciation.emoji}</span>
                                                            {c.appreciation.libelle}
                                                        </p>
                                                        {c.appreciation.commentaire && (
                                                            <p className="mt-0.5 line-clamp-2 text-[11px] italic text-slate-600">
                                                                « {c.appreciation.commentaire} »
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </button>

                                            {/* Annuler reste possible même une fois la course
                                                attribuée : c'est souvent à ce moment qu'on
                                                apprend que le client a renoncé. */}
                                            {c.annulable && (
                                                <div className="px-4 pb-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => setAnnulationDemandee(c.id)}
                                                        className="w-full rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                    >
                                                        Annuler la course
                                                    </button>
                                                </div>
                                            )}

                                            {c.cancel_reason && (
                                                <p className="px-4 pb-3 text-xs text-red-700">
                                                    Annulée : {c.cancel_reason}
                                                </p>
                                            )}

                                            {c.attribuable && (
                                                <div className="px-4 pb-3">
                                                    {attribution === c.id ? (
                                                        <ChoixAgent
                                                            agents={agentsDisponibles}
                                                            enCours={enCoursAttribution}
                                                            surChoix={(agent) => attribuer(c.id, agent)}
                                                            surAnnuler={() => setAttribution(null)}
                                                        />
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => setAttribution(c.id)}
                                                            className="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700"
                                                        >
                                                            Attribuer à un agent
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </aside>

                    <div className="relative min-w-0 flex-1">
                        <div ref={conteneurRef} className="h-full w-full" />

                        <div className="pointer-events-none absolute bottom-4 left-4 z-[400] rounded-lg bg-white/95 px-3 py-2 text-xs shadow-lg ring-1 ring-slate-200">
                            <p className="mb-1 font-semibold text-slate-700">Légende</p>
                            <p className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-emerald-500" /> Agent suivi en direct
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-slate-400" /> Agent, position dormante
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-red-500" /> Demande sans agent
                            </p>
                        </div>

                        {!enLigne && (
                            <div className="absolute left-1/2 top-4 z-[400] -translate-x-1/2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-lg">
                                Contact perdu — les positions affichées ne sont plus à jour
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <audio ref={audioRef} preload="auto">
                <source src="/sounds/notification.mp3" type="audio/mpeg" />
                <source src="/sounds/notification.wav" type="audio/wav" />
            </audio>

            <DemandeDeMotif
                ouvert={annulationDemandee !== null}
                titre="Annuler cette course"
                motifs={donnees.motifs_annulation ?? []}
                enCours={enCoursAnnulation}
                onAnnuler={() => setAnnulationDemandee(null)}
                onValider={(motif) => annuler(annulationDemandee, motif)}
            />
        </>
    );
}
