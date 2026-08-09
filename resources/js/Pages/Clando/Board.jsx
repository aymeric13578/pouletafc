import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/*
 * Carte des courses clando, en accès libre comme le mur des commandes.
 *
 * Contraintes qui ont guidé les choix :
 *  - la carte ne doit jamais être reconstruite. Leaflet est piloté en direct via
 *    des refs plutôt que recréé à chaque rendu React : on déplace les marqueurs
 *    existants. Reconstruire ferait clignoter la carte toutes les quatre secondes
 *    et perdrait le zoom, la position et la fiche ouverte ;
 *  - une nouvelle demande doit s'entendre et se voir. Même politique audio que le
 *    mur des commandes : le navigateur exige un geste utilisateur, on mémorise la
 *    préférence et on retente à l'ouverture ;
 *  - une coupure réseau ne doit pas laisser croire que les agents sont immobiles.
 *    L'écran affiche explicitement la perte de contact ;
 *  - un point figé ne doit pas passer pour un suivi en direct. Seuls les agents
 *    engagés sur une course sont réellement suivis (position poussée toutes les
 *    trois secondes) ; les autres sont au dernier point connu du démarrage de
 *    journée et sont affichés différemment.
 */

// L'application pousse les positions toutes les 3 s : interroger plus vite ne
// donnerait rien de neuf, plus lentement ferait sauter les marqueurs.
const INTERVALLE_MS = 4000;
const RAPPEL_SONORE_MS = 20000;
const CLE_SON = 'carte-clando.son-active';

// Douala, centre par défaut tant qu'aucun point n'est disponible.
const CENTRE_DEFAUT = [4.0511, 9.7679];
const ZOOM_DEFAUT = 12;

const STATUTS = {
    pending: { label: 'En attente', couleur: '#f59e0b', classe: 'bg-amber-100 text-amber-800 ring-amber-300' },
    want: { label: 'Demandée', couleur: '#ef4444', classe: 'bg-red-100 text-red-800 ring-red-300' },
    take: { label: 'Prise en charge', couleur: '#6366f1', classe: 'bg-indigo-100 text-indigo-800 ring-indigo-300' },
    process: { label: 'En cours', couleur: '#0ea5e9', classe: 'bg-sky-100 text-sky-800 ring-sky-300' },
    Success: { label: 'Terminée', couleur: '#10b981', classe: 'bg-emerald-100 text-emerald-800 ring-emerald-300' },
    declin: { label: 'Refusée', couleur: '#94a3b8', classe: 'bg-slate-100 text-slate-700 ring-slate-300' },
    failed: { label: 'Échec', couleur: '#dc2626', classe: 'bg-red-100 text-red-800 ring-red-300' },
};

const couleurStatut = (statut) => STATUTS[statut]?.couleur ?? '#64748b';

const formatMontant = (valeur) => new Intl.NumberFormat('fr-FR').format(valeur ?? 0);

/*
 * Marqueurs dessinés en HTML plutôt qu'en images.
 *
 * Les icônes Leaflet par défaut pointent vers des fichiers résolus relativement
 * au CSS ; avec Vite, ces chemins cassent et les marqueurs disparaissent. Des
 * divIcon évitent le problème et permettent en prime de colorer un marqueur selon
 * son statut sans embarquer une image par couleur.
 */
const iconeAgent = (frais) =>
    L.divIcon({
        className: '',
        html: `<div class="relative flex h-7 w-7 items-center justify-center">
            ${frais ? '<span class="absolute inline-flex h-7 w-7 animate-ping rounded-full bg-emerald-400 opacity-60"></span>' : ''}
            <span class="relative inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-white text-white shadow-lg"
                  style="background:${frais ? '#10b981' : '#94a3b8'}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-4 w-4">
                    <path d="M5 17h2m10 0h2M6 17a2 2 0 104 0 2 2 0 10-4 0m8 0a2 2 0 104 0 2 2 0 10-4 0M4 13l1.5-5h9l3 5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });

const iconeClient = (statut, enAttente) =>
    L.divIcon({
        className: '',
        html: `<div class="relative flex h-7 w-7 items-center justify-center">
            ${enAttente ? '<span class="absolute inline-flex h-7 w-7 animate-ping rounded-full opacity-60" style="background:' + couleurStatut(statut) + '"></span>' : ''}
            <span class="relative inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-white text-white shadow-lg"
                  style="background:${couleurStatut(statut)}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-4 w-4">
                    <path d="M16 20v-1a4 4 0 00-8 0v1M12 11a3 3 0 100-6 3 3 0 000 6z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });

const iconeDestination = (statut) =>
    L.divIcon({
        className: '',
        html: `<span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white text-white shadow-lg"
                     style="background:${couleurStatut(statut)}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-3.5 w-3.5">
                <path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="10" r="2.4"/>
            </svg>
        </span>`,
        iconSize: [24, 24],
        iconAnchor: [12, 24],
    });

const echapper = (texte) =>
    String(texte ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]),
    );

export default function Board({ initial }) {
    const [donnees, setDonnees] = useState(initial);
    const [enLigne, setEnLigne] = useState(true);
    const [sonActif, setSonActif] = useState(false);
    const [silence, setSilence] = useState(false);
    const [heure, setHeure] = useState(new Date());
    const [recherche, setRecherche] = useState('');
    const [filtreStatut, setFiltreStatut] = useState('actives');
    const [selection, setSelection] = useState(null);
    const [nouvelles, setNouvelles] = useState(new Set());
    const [suivi, setSuivi] = useState(true);
    const [couches, setCouches] = useState({ agents: true, clients: true, destinations: true, trajets: true });

    const audioRef = useRef(null);
    const sonActifRef = useRef(false);
    const silenceRef = useRef(false);
    const dernierIdRef = useRef(initial.latest_id ?? 0);

    // Instance Leaflet et calques, tenus hors du cycle de rendu React.
    const carteRef = useRef(null);
    const conteneurRef = useRef(null);
    const marqueursAgents = useRef(new Map());
    const marqueursClients = useRef(new Map());
    const marqueursDestinations = useRef(new Map());
    const tracesRef = useRef(new Map());
    const cadrageInitialRef = useRef(false);
    const selectionRef = useRef(null);

    useEffect(() => {
        const timer = setInterval(() => setHeure(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        selectionRef.current = selection;
    }, [selection]);

    const courses = donnees.courses ?? [];
    const agents = donnees.agents ?? [];
    const stats = donnees.stats ?? {};

    /* ------------------------------------------------------------------ son */

    const jouerAlerte = useCallback(() => {
        if (!sonActifRef.current || silenceRef.current || !audioRef.current) return;

        audioRef.current.currentTime = 0;
        audioRef.current.play().catch(() => {
            sonActifRef.current = false;
            setSonActif(false);
        });
    }, []);

    /*
     * Débloque l'audio et mémorise le choix. Le navigateur exige un geste
     * utilisateur par chargement de page : aucun code ne contourne cette règle.
     * On retente donc silencieusement à chaque ouverture, et n'importe quel clic
     * suffit ensuite à débloquer.
     */
    const activerSon = useCallback(async () => {
        if (!audioRef.current) return false;

        try {
            await audioRef.current.play();
            audioRef.current.pause();
            audioRef.current.currentTime = 0;
            sonActifRef.current = true;
            setSonActif(true);
            try {
                localStorage.setItem(CLE_SON, '1');
            } catch {
                // Navigation privée : on continue sans mémoriser.
            }
            return true;
        } catch {
            sonActifRef.current = false;
            setSonActif(false);
            return false;
        }
    }, []);

    useEffect(() => {
        let prefere = false;
        try {
            prefere = localStorage.getItem(CLE_SON) === '1';
        } catch {
            prefere = false;
        }

        if (!prefere) return undefined;

        let annule = false;
        activerSon().then((ok) => {
            if (annule || ok) return;

            // Le navigateur a refusé : le premier clic venu sur la page débloque,
            // plutôt que d'obliger à viser le bouton.
            const surGeste = () => {
                activerSon();
                document.removeEventListener('pointerdown', surGeste);
            };
            document.addEventListener('pointerdown', surGeste);
        });

        return () => {
            annule = true;
        };
    }, [activerSon]);

    const nbEnAttente = useMemo(() => courses.filter((c) => c.en_attente).length, [courses]);

    // Rappel tant qu'une demande n'est pas prise en charge.
    useEffect(() => {
        if (nbEnAttente === 0) return undefined;

        const timer = setInterval(jouerAlerte, RAPPEL_SONORE_MS);
        return () => clearInterval(timer);
    }, [jouerAlerte, nbEnAttente]);

    // Le silence se lève de lui-même quand la file se vide.
    useEffect(() => {
        if (silenceRef.current && nbEnAttente === 0) {
            silenceRef.current = false;
            setSilence(false);
        }
    }, [nbEnAttente]);

    /* ---------------------------------------------------------------- flux */

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
                    silenceRef.current = false;
                    setSilence(false);

                    jouerAlerte();
                }
            } catch {
                if (!annule) setEnLigne(false);
            }
        };

        const timer = setInterval(recuperer, INTERVALLE_MS);
        return () => {
            annule = true;
            clearInterval(timer);
        };
    }, [jouerAlerte]);

    /* --------------------------------------------------------------- carte */

    useEffect(() => {
        if (carteRef.current || !conteneurRef.current) return undefined;

        const carte = L.map(conteneurRef.current, {
            center: CENTRE_DEFAUT,
            zoom: ZOOM_DEFAUT,
            zoomControl: true,
            attributionControl: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(carte);

        // Déplacer la carte à la main coupe le recadrage automatique : sinon le
        // cycle suivant ramènerait la vue et la carte serait impossible à explorer.
        carte.on('dragstart', () => setSuivi(false));

        carteRef.current = carte;

        return () => {
            carte.remove();
            carteRef.current = null;
        };
    }, []);

    const coursesFiltrees = useMemo(() => {
        const terme = recherche.trim().toLowerCase();

        return courses.filter((c) => {
            if (filtreStatut === 'actives' && !c.active) return false;
            if (filtreStatut === 'attente' && !c.en_attente) return false;
            if (!['actives', 'attente', 'toutes'].includes(filtreStatut) && c.status !== filtreStatut) return false;

            if (!terme) return true;

            return [c.ref, c.destination, c.client?.name, c.agent?.name]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(terme));
        });
    }, [courses, filtreStatut, recherche]);

    /*
     * Synchronise les marqueurs avec les données.
     *
     * On réutilise les marqueurs existants en les déplaçant : les recréer à chaque
     * cycle ferait disparaître puis réapparaître les points toutes les quatre
     * secondes, et fermerait la fiche ouverte.
     */
    const synchroniser = useCallback((cle, registre, elements, fabriquer, majExistant) => {
        const carte = carteRef.current;
        if (!carte) return;

        const vus = new Set();

        elements.forEach((element) => {
            vus.add(element.cle);
            const existant = registre.current.get(element.cle);

            if (existant) {
                majExistant(existant, element);
            } else {
                const marqueur = fabriquer(element);
                marqueur.addTo(carte);
                registre.current.set(element.cle, marqueur);
            }
        });

        // Retrait de ce qui a disparu du flux : une course terminée ne doit pas
        // laisser un marqueur fantôme sur la carte.
        registre.current.forEach((marqueur, id) => {
            if (!vus.has(id)) {
                carte.removeLayer(marqueur);
                registre.current.delete(id);
            }
        });
    }, []);

    // Agents
    useEffect(() => {
        const carte = carteRef.current;
        if (!carte) return;

        const elements = couches.agents
            ? agents
                  .filter((a) => a.lat !== null && a.lon !== null)
                  .map((a) => ({ ...a, cle: `agent-${a.id}` }))
            : [];

        synchroniser(
            'agents',
            marqueursAgents,
            elements,
            (a) =>
                L.marker([a.lat, a.lon], { icon: iconeAgent(a.frais), zIndexOffset: 500 }).bindPopup(
                    `<div class="text-sm">
                        <div class="font-semibold">${echapper(a.name)}</div>
                        <div class="text-slate-500">${echapper(a.phone ?? '')}</div>
                        <div class="mt-1">${a.frais ? 'Suivi en direct · course ' + echapper(a.course_ref) : 'Dernier point connu (démarrage de journée)'}</div>
                    </div>`,
                ),
            (marqueur, a) => {
                marqueur.setLatLng([a.lat, a.lon]);
                marqueur.setIcon(iconeAgent(a.frais));
                marqueur.setPopupContent(
                    `<div class="text-sm">
                        <div class="font-semibold">${echapper(a.name)}</div>
                        <div class="text-slate-500">${echapper(a.phone ?? '')}</div>
                        <div class="mt-1">${a.frais ? 'Suivi en direct · course ' + echapper(a.course_ref) : 'Dernier point connu (démarrage de journée)'}</div>
                    </div>`,
                );
            },
        );
    }, [agents, couches.agents, synchroniser]);

    // Clients (point de départ de la course)
    useEffect(() => {
        const carte = carteRef.current;
        if (!carte) return;

        const elements = couches.clients
            ? coursesFiltrees
                  .filter((c) => c.depart)
                  .map((c) => ({ ...c, cle: `client-${c.id}` }))
            : [];

        const contenu = (c) =>
            `<div class="text-sm">
                <div class="font-semibold">${echapper(c.client?.name ?? 'Client')}</div>
                <div class="text-slate-500">${echapper(c.client?.phone ?? '')}</div>
                <div class="mt-1">${echapper(c.ref)} · ${echapper(c.status_label)}</div>
                <div>${formatMontant(c.price)} FCFA${c.destination ? ' · ' + echapper(c.destination) : ''}</div>
            </div>`;

        synchroniser(
            'clients',
            marqueursClients,
            elements,
            (c) =>
                L.marker([c.depart.lat, c.depart.lon], {
                    icon: iconeClient(c.status, c.en_attente),
                    zIndexOffset: 400,
                })
                    .bindPopup(contenu(c))
                    .on('click', () => setSelection(c.id)),
            (marqueur, c) => {
                marqueur.setLatLng([c.depart.lat, c.depart.lon]);
                marqueur.setIcon(iconeClient(c.status, c.en_attente));
                marqueur.setPopupContent(contenu(c));
            },
        );
    }, [coursesFiltrees, couches.clients, synchroniser]);

    // Destinations
    useEffect(() => {
        const carte = carteRef.current;
        if (!carte) return;

        const elements = couches.destinations
            ? coursesFiltrees
                  .filter((c) => c.arrivee)
                  .map((c) => ({ ...c, cle: `dest-${c.id}` }))
            : [];

        synchroniser(
            'destinations',
            marqueursDestinations,
            elements,
            (c) =>
                L.marker([c.arrivee.lat, c.arrivee.lon], {
                    icon: iconeDestination(c.status),
                    zIndexOffset: 300,
                }).bindPopup(
                    `<div class="text-sm"><div class="font-semibold">Destination</div><div>${echapper(c.destination ?? '—')}</div><div class="text-slate-500">${echapper(c.ref)}</div></div>`,
                ),
            (marqueur, c) => {
                marqueur.setLatLng([c.arrivee.lat, c.arrivee.lon]);
                marqueur.setIcon(iconeDestination(c.status));
            },
        );
    }, [coursesFiltrees, couches.destinations, synchroniser]);

    // Trajets : départ → destination, et agent → départ tant qu'il n'y est pas.
    useEffect(() => {
        const carte = carteRef.current;
        if (!carte) return;

        const elements = couches.trajets
            ? coursesFiltrees
                  .filter((c) => c.depart && c.arrivee)
                  .map((c) => ({ ...c, cle: `trace-${c.id}` }))
            : [];

        synchroniser(
            'traces',
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

    /*
     * Recadrage automatique sur l'ensemble des points.
     *
     * Volontairement limité au premier chargement et aux demandes explicites : un
     * recadrage à chaque cycle rendrait la carte inutilisable, la vue sautant
     * toutes les quatre secondes dès qu'un agent bouge.
     */
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
    }, [agents, coursesFiltrees]);

    useEffect(() => {
        if (cadrageInitialRef.current) return;
        if (agents.length === 0 && coursesFiltrees.length === 0) return;

        cadrageInitialRef.current = true;
        recadrer();
    }, [agents.length, coursesFiltrees.length, recadrer]);

    // Centrage sur la course choisie dans la liste.
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

        setSuivi(false);
        carte.fitBounds(L.latLngBounds(points), { padding: [80, 80], maxZoom: 16 });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection]);

    const basculerCouche = (nom) => setCouches((c) => ({ ...c, [nom]: !c[nom] }));

    const couperSon = () => {
        silenceRef.current = true;
        setSilence(true);
    };

    const filtres = [
        { cle: 'actives', libelle: 'En cours' },
        { cle: 'attente', libelle: 'Sans agent' },
        { cle: 'toutes', libelle: 'Toutes' },
    ];

    return (
        <>
            <Head title="Carte clando" />

            <div className="flex h-screen flex-col overflow-hidden bg-slate-100">
                {/* En-tête */}
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
                        <Compteur libelle="Du jour" valeur={stats.du_jour ?? 0} ton="slate" />
                        <Compteur libelle="CA du jour" valeur={`${formatMontant(stats.ca_jour)} F`} ton="slate" />
                    </div>

                    <div className="ml-auto flex items-center gap-2">
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
                                onClick={activerSon}
                                className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700"
                            >
                                Activer le son
                            </button>
                        )}

                        {sonActif && nbEnAttente > 0 && !silence && (
                            <button
                                type="button"
                                onClick={couperSon}
                                className="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600"
                            >
                                Couper le son
                            </button>
                        )}
                    </div>
                </header>

                <div className="flex min-h-0 flex-1">
                    {/* Panneau latéral : les menus qui agissent sur la carte */}
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
                                            onChange={() => basculerCouche(c.cle)}
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
                                    setSuivi(true);
                                    recadrer();
                                }}
                                className="w-full rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                            >
                                Recadrer sur tout
                            </button>
                        </div>

                        {/* Liste des courses */}
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {coursesFiltrees.length === 0 ? (
                                <p className="p-6 text-center text-sm text-slate-400">Aucune course</p>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {coursesFiltrees.map((c) => (
                                        <li key={c.id}>
                                            <button
                                                type="button"
                                                onClick={() => setSelection(c.id === selection ? null : c.id)}
                                                className={`w-full px-4 py-3 text-left transition ${
                                                    selection === c.id ? 'bg-slate-100' : 'hover:bg-slate-50'
                                                } ${nouvelles.has(c.id) ? 'animate-pulse bg-amber-50' : ''}`}
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
                                                    <span>
                                                        {c.agent?.name ? `Agent : ${c.agent.name}` : 'Aucun agent'}
                                                    </span>
                                                    <span className="tabular-nums">
                                                        {formatMontant(c.price)} F · {c.created_label}
                                                    </span>
                                                </div>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </aside>

                    {/* Carte */}
                    <div className="relative min-w-0 flex-1">
                        <div ref={conteneurRef} className="h-full w-full" />

                        {/* Légende */}
                        <div className="pointer-events-none absolute bottom-4 left-4 z-[400] rounded-lg bg-white/95 px-3 py-2 text-xs shadow-lg ring-1 ring-slate-200">
                            <p className="mb-1 font-semibold text-slate-700">Légende</p>
                            <p className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-emerald-500" /> Agent suivi en direct
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-slate-400" /> Agent, dernier point connu
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
        </>
    );
}

function Compteur({ libelle, valeur, ton }) {
    const tons = {
        sky: 'bg-sky-50 text-sky-700 ring-sky-200',
        red: 'bg-red-50 text-red-700 ring-red-200',
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        slate: 'bg-slate-50 text-slate-700 ring-slate-200',
    };

    return (
        <span className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ${tons[ton] ?? tons.slate}`}>
            {libelle} <span className="ml-1 text-sm font-bold tabular-nums">{valeur}</span>
        </span>
    );
}
