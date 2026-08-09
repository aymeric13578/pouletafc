import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
} from '../Shared/carte';

/*
 * Carte des livraisons : activité des agents et points de livraison.
 *
 * Le mur des commandes dit où en est une commande, jamais où elle va. Le
 * comptoir voyait une adresse en texte sans pouvoir juger quel agent en était le
 * plus proche. Cet écran répond à cette question, et permet de confier la
 * commande dans la foulée.
 *
 * Trois points par commande, qui ne se confondent pas : la boutique d'où part le
 * colis, le point de livraison où il va, et l'agent qui roule entre les deux.
 */

const INTERVALLE_MS = 4000;
const RAPPEL_SONORE_MS = 20000;
const CLE_SON = 'carte-livraisons.son-active';

const STATUTS = {
    pending: { couleur: '#f59e0b', classe: 'bg-amber-100 text-amber-800 ring-amber-300' },
    waiting: { couleur: '#8b5cf6', classe: 'bg-violet-100 text-violet-800 ring-violet-300' },
    want: { couleur: '#ef4444', classe: 'bg-red-100 text-red-800 ring-red-300' },
    take: { couleur: '#6366f1', classe: 'bg-indigo-100 text-indigo-800 ring-indigo-300' },
    process: { couleur: '#0ea5e9', classe: 'bg-sky-100 text-sky-800 ring-sky-300' },
    Success: { couleur: '#10b981', classe: 'bg-emerald-100 text-emerald-800 ring-emerald-300' },
    failed: { couleur: '#dc2626', classe: 'bg-red-100 text-red-800 ring-red-300' },
};

const couleurStatut = (statut) => STATUTS[statut]?.couleur ?? '#64748b';

const jetonCsrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function Map({ initial }) {
    const [donnees, setDonnees] = useState(initial);
    const [enLigne, setEnLigne] = useState(true);
    const [heure, setHeure] = useState(new Date());
    const [recherche, setRecherche] = useState('');
    const [filtreStatut, setFiltreStatut] = useState('actives');
    const [selection, setSelection] = useState(null);
    const [nouvelles, setNouvelles] = useState(new Set());
    const [couches, setCouches] = useState({ agents: true, livraisons: true, boutiques: true, trajets: true });
    const [attribution, setAttribution] = useState(null);
    const [enCoursAttribution, setEnCoursAttribution] = useState(false);
    const [retour, setRetour] = useState(null);

    const dernierIdRef = useRef(initial.latest_id ?? 0);
    const conteneurRef = useRef(null);
    const marqueursAgents = useRef(new Map());
    const marqueursLivraisons = useRef(new Map());
    const marqueursBoutiques = useRef(new Map());
    const tracesRef = useRef(new Map());
    const cadrageInitialRef = useRef(false);

    const { audioRef, sonActif, silence, jouer, activer, couper, leverSilence } = useAlerteSonore(CLE_SON);

    const carteRef = useCarteLeaflet(conteneurRef, { surDeplacement: () => setSelection(null) });
    const synchroniser = useSynchronisation(carteRef);

    useEffect(() => {
        const timer = setInterval(() => setHeure(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    const orders = donnees.orders ?? [];
    const agents = donnees.agents ?? [];
    const agentsDisponibles = donnees.agents_disponibles ?? [];
    const stats = donnees.stats ?? {};

    const nbEnAttente = useMemo(() => orders.filter((o) => o.en_attente).length, [orders]);

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
                const fraiches = (charge.orders ?? [])
                    .filter((o) => o.id > dernierIdRef.current)
                    .map((o) => o.id);

                dernierIdRef.current = idMax;
                setNouvelles((precedent) => new Set([...precedent, ...fraiches]));

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
                const reponse = await fetch('/commandes/carte/flux', {
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
        async (orderId, agent) => {
            setEnCoursAttribution(true);
            setRetour(null);

            try {
                const reponse = await fetch(`/commandes/carte/${orderId}/agent`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': jetonCsrf(),
                    },
                    body: JSON.stringify({ id_agent: agent.id_user }),
                });

                const charge = await reponse.json();

                if (charge.orders) appliquer(charge);

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

    useEffect(() => {
        if (!retour) return undefined;
        const timer = setTimeout(() => setRetour(null), 6000);
        return () => clearTimeout(timer);
    }, [retour]);

    /* -------------------------------------------------------------- filtres */

    const termeRetarde = useDebounce(recherche.trim().toLowerCase(), 150);

    const ordersFiltrees = useMemo(() => {
        return orders.filter((o) => {
            if (filtreStatut === 'actives' && !o.active) return false;
            if (filtreStatut === 'attente' && !o.en_attente) return false;
            if (filtreStatut === 'sans_agent' && !o.attribuable) return false;
            if (!['actives', 'attente', 'sans_agent', 'toutes'].includes(filtreStatut) && o.status !== filtreStatut)
                return false;

            if (!termeRetarde) return true;

            return [o.ref, o.address, o.customer?.name, o.agent?.name]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(termeRetarde));
        });
    }, [orders, filtreStatut, termeRetarde]);

    /* ------------------------------------------------------------ marqueurs */

    useEffect(() => {
        const elements = couches.agents
            ? agents.filter((a) => a.lat !== null && a.lon !== null).map((a) => ({ ...a, cle: `agent-${a.id}` }))
            : [];

        const contenu = (a) =>
            `<div class="text-sm">
                <div class="font-semibold">${echapper(a.name)}</div>
                <div class="text-slate-500">${echapper(a.phone ?? '')}</div>
                <div class="mt-1">${a.frais ? 'Suivi en direct · commande ' + echapper(a.commande_ref) : 'Dernier point connu (démarrage de journée)'}</div>
            </div>`;

        synchroniser(
            marqueursAgents,
            elements,
            (a) => L.marker([a.lat, a.lon], { icon: iconeAgent(a.frais), zIndexOffset: 500 }).bindPopup(contenu(a)),
            (marqueur, a) => {
                marqueur.setLatLng([a.lat, a.lon]);
                marqueur.setIcon(iconeAgent(a.frais));
                marqueur.setPopupContent(contenu(a));
            },
        );
    }, [agents, couches.agents, synchroniser]);

    useEffect(() => {
        const elements = couches.livraisons
            ? ordersFiltrees.filter((o) => o.livraison).map((o) => ({ ...o, cle: `livr-${o.id}` }))
            : [];

        const contenu = (o) =>
            `<div class="text-sm">
                <div class="font-semibold">${echapper(o.customer?.name ?? 'Client')}</div>
                <div class="text-slate-500">${echapper(o.customer?.phone ?? '')}</div>
                <div class="mt-1">${echapper(o.ref)} · ${echapper(o.status_label)}</div>
                <div>${echapper(o.address ?? 'Adresse non renseignée')}</div>
                <div>${formatMontant(o.price)} FCFA</div>
            </div>`;

        synchroniser(
            marqueursLivraisons,
            elements,
            (o) =>
                L.marker([o.livraison.lat, o.livraison.lon], {
                    icon: iconePoint(couleurStatut(o.status), 'client', o.en_attente),
                    zIndexOffset: 400,
                })
                    .bindPopup(contenu(o))
                    .on('click', () => setSelection(o.id)),
            (marqueur, o) => {
                marqueur.setLatLng([o.livraison.lat, o.livraison.lon]);
                marqueur.setIcon(iconePoint(couleurStatut(o.status), 'client', o.en_attente));
                marqueur.setPopupContent(contenu(o));
            },
        );
    }, [ordersFiltrees, couches.livraisons, synchroniser]);

    useEffect(() => {
        const elements = couches.boutiques
            ? ordersFiltrees.filter((o) => o.boutique).map((o) => ({ ...o, cle: `shop-${o.id}` }))
            : [];

        synchroniser(
            marqueursBoutiques,
            elements,
            (o) =>
                L.marker([o.boutique.lat, o.boutique.lon], {
                    icon: iconePoint('#475569', 'boutique'),
                    zIndexOffset: 200,
                }).bindPopup(
                    `<div class="text-sm"><div class="font-semibold">Point de retrait</div><div class="text-slate-500">${echapper(o.ref)}</div></div>`,
                ),
            (marqueur, o) => marqueur.setLatLng([o.boutique.lat, o.boutique.lon]),
        );
    }, [ordersFiltrees, couches.boutiques, synchroniser]);

    useEffect(() => {
        const elements = couches.trajets
            ? ordersFiltrees.filter((o) => o.boutique && o.livraison).map((o) => ({ ...o, cle: `trace-${o.id}` }))
            : [];

        synchroniser(
            tracesRef,
            elements,
            (o) =>
                L.polyline(
                    [
                        [o.boutique.lat, o.boutique.lon],
                        [o.livraison.lat, o.livraison.lon],
                    ],
                    { color: couleurStatut(o.status), weight: 3, opacity: 0.6, dashArray: '6 8' },
                ),
            (trace, o) => {
                trace.setLatLngs([
                    [o.boutique.lat, o.boutique.lon],
                    [o.livraison.lat, o.livraison.lon],
                ]);
                trace.setStyle({ color: couleurStatut(o.status) });
            },
        );
    }, [ordersFiltrees, couches.trajets, synchroniser]);

    /* --------------------------------------------------------------- cadrage */

    const recadrer = useCallback(() => {
        const carte = carteRef.current;
        if (!carte) return;

        const points = [];
        agents.forEach((a) => a.lat !== null && a.lon !== null && points.push([a.lat, a.lon]));
        ordersFiltrees.forEach((o) => {
            if (o.livraison) points.push([o.livraison.lat, o.livraison.lon]);
            if (o.boutique) points.push([o.boutique.lat, o.boutique.lon]);
        });

        if (points.length === 0) return;

        carte.fitBounds(L.latLngBounds(points), { padding: [60, 60], maxZoom: 15 });
    }, [agents, carteRef, ordersFiltrees]);

    useEffect(() => {
        if (cadrageInitialRef.current) return;
        if (agents.length === 0 && ordersFiltrees.length === 0) return;

        cadrageInitialRef.current = true;
        recadrer();
    }, [agents.length, ordersFiltrees.length, recadrer]);

    useEffect(() => {
        const carte = carteRef.current;
        if (!carte || selection === null) return;

        const commande = orders.find((o) => o.id === selection);
        if (!commande) return;

        const points = [];
        if (commande.livraison) points.push([commande.livraison.lat, commande.livraison.lon]);
        if (commande.boutique) points.push([commande.boutique.lat, commande.boutique.lon]);
        if (commande.agent_position) points.push([commande.agent_position.lat, commande.agent_position.lon]);

        if (points.length === 0) return;

        carte.fitBounds(L.latLngBounds(points), { padding: [80, 80], maxZoom: 16 });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection]);

    const filtres = [
        { cle: 'actives', libelle: 'En cours' },
        { cle: 'sans_agent', libelle: 'Sans livreur' },
        { cle: 'toutes', libelle: 'Toutes' },
    ];

    return (
        <>
            <Head title="Carte livraisons" />

            <div className="flex h-screen flex-col overflow-hidden bg-slate-100">
                <header className="flex flex-wrap items-center gap-4 border-b border-slate-200 bg-white px-5 py-3 shadow-sm">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-900">Carte livraisons</h1>
                        <p className="text-xs text-slate-500">
                            {donnees.server_date} · {donnees.server_time}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Compteur libelle="Commandes actives" valeur={stats.actives ?? 0} ton="sky" />
                        <Compteur libelle="À prendre" valeur={stats.en_attente ?? 0} ton="red" />
                        <Compteur libelle="Agents en service" valeur={stats.agents_actifs ?? 0} ton="emerald" />
                        <Compteur libelle="Du jour" valeur={stats.du_jour ?? 0} />
                        <Compteur libelle="CA du jour" valeur={`${formatMontant(stats.ca_jour)} F`} />
                    </div>

                    <div className="ml-auto flex items-center gap-2">
                        <a
                            href="/commandes"
                            className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                        >
                            Mur des commandes
                        </a>
                        <a
                            href="/clando"
                            className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                        >
                            Carte clando
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
                                placeholder="Référence, client, livreur, adresse"
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
                                    { cle: 'livraisons', libelle: 'Points de livraison' },
                                    { cle: 'boutiques', libelle: 'Points de retrait' },
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
                            {ordersFiltrees.length === 0 ? (
                                <p className="p-6 text-center text-sm text-slate-400">Aucune commande</p>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {ordersFiltrees.map((o) => (
                                        <li key={o.id} className={nouvelles.has(o.id) ? 'bg-amber-50' : ''}>
                                            <button
                                                type="button"
                                                onClick={() => setSelection(o.id === selection ? null : o.id)}
                                                className={`w-full px-4 py-3 text-left transition ${
                                                    selection === o.id ? 'bg-slate-100' : 'hover:bg-slate-50'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="truncate text-sm font-semibold text-slate-900">
                                                        {o.customer?.name ?? 'Client'}
                                                    </span>
                                                    <span
                                                        className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ${
                                                            STATUTS[o.status]?.classe ??
                                                            'bg-slate-100 text-slate-700 ring-slate-300'
                                                        }`}
                                                    >
                                                        {o.status_label}
                                                    </span>
                                                </div>
                                                <p className="mt-0.5 truncate text-xs text-slate-500">
                                                    {o.address || 'Adresse non renseignée'}
                                                </p>
                                                <div className="mt-1 flex items-center justify-between text-xs text-slate-400">
                                                    <span>{o.agent?.name ? `Livreur : ${o.agent.name}` : 'Aucun livreur'}</span>
                                                    <span className="tabular-nums">
                                                        {formatMontant(o.price)} F · {o.created_label}
                                                    </span>
                                                </div>
                                            </button>

                                            {o.attribuable && (
                                                <div className="px-4 pb-3">
                                                    {attribution === o.id ? (
                                                        <ChoixAgent
                                                            agents={agentsDisponibles}
                                                            enCours={enCoursAttribution}
                                                            surChoix={(agent) => attribuer(o.id, agent)}
                                                            surAnnuler={() => setAttribution(null)}
                                                        />
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => setAttribution(o.id)}
                                                            className="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700"
                                                        >
                                                            Attribuer à un livreur
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
                                <span className="h-3 w-3 rounded-full bg-slate-400" /> Agent, dernier point connu
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-slate-600" /> Point de retrait
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-red-500" /> Livraison sans livreur
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
