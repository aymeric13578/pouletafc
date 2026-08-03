import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';

/*
 * Mur des commandes pour affichage télévision.
 *
 * Contraintes qui ont guidé les choix :
 *  - lisible à plusieurs mètres : gros caractères, tableau ordonné, fort contraste ;
 *  - jamais de rechargement de page : il couperait le son et perdrait l'état.
 *    On interroge un flux JSON et on remplace les données en mémoire ;
 *  - le son ne peut pas démarrer seul : tous les navigateurs bloquent la lecture
 *    audio tant que l'utilisateur n'a pas interagi avec la page. D'où le bouton
 *    d'activation, à cliquer une fois quand on installe l'écran ;
 *  - une coupure réseau ne doit pas laisser croire que tout va bien : l'écran
 *    affiche explicitement qu'il a perdu le contact.
 */

const INTERVALLE_MS = 5000;
const RAPPEL_SONORE_MS = 20000;
const CLE_SON = 'mur-commandes.son-active';

/*
 * Statuts considérés comme « pas encore prise en charge » : la sonnerie se répète
 * tant qu'au moins une commande s'y trouve. « take » et « process » signifient
 * qu'un agent s'en occupe, l'alerte s'arrête donc.
 */
const STATUTS_EN_ATTENTE = ['pending', 'want'];

const STATUTS = {
    pending: { label: 'En attente', classe: 'bg-amber-100 text-amber-800 ring-amber-300' },
    want: { label: 'À prendre', classe: 'bg-orange-100 text-orange-800 ring-orange-300' },
    process: { label: 'En cours', classe: 'bg-sky-100 text-sky-800 ring-sky-300' },
    take: { label: 'Prise en charge', classe: 'bg-indigo-100 text-indigo-800 ring-indigo-300' },
    Success: { label: 'Livrée', classe: 'bg-emerald-100 text-emerald-800 ring-emerald-300' },
    failed: { label: 'Échec', classe: 'bg-red-100 text-red-800 ring-red-300' },
};

const formatMontant = (valeur) => new Intl.NumberFormat('fr-FR').format(valeur ?? 0);

/*
 * Actions proposées selon l'état courant. On n'affiche que les transitions qui
 * ont un sens : proposer « Terminer » sur une commande déjà livrée, ou « Prendre »
 * sur une commande annulée, encombre l'écran et invite à l'erreur.
 */
const ACTIONS = {
    pending: [
        { statut: 'process', libelle: 'Prendre', variante: 'sky' },
        { statut: 'Success', libelle: 'Terminer', variante: 'emerald' },
        { statut: 'failed', libelle: 'Annuler', variante: 'red' },
    ],
    want: [
        { statut: 'process', libelle: 'Prendre', variante: 'sky' },
        { statut: 'Success', libelle: 'Terminer', variante: 'emerald' },
        { statut: 'failed', libelle: 'Annuler', variante: 'red' },
    ],
    take: [
        { statut: 'Success', libelle: 'Terminer', variante: 'emerald' },
        { statut: 'failed', libelle: 'Annuler', variante: 'red' },
    ],
    process: [
        { statut: 'Success', libelle: 'Terminer', variante: 'emerald' },
        { statut: 'failed', libelle: 'Annuler', variante: 'red' },
    ],
    Success: [{ statut: 'process', libelle: 'Rouvrir', variante: 'gris' }],
    failed: [{ statut: 'process', libelle: 'Rouvrir', variante: 'gris' }],
};

const VARIANTES = {
    sky: 'bg-sky-600 text-white hover:bg-sky-700',
    emerald: 'bg-emerald-600 text-white hover:bg-emerald-700',
    red: 'bg-red-600 text-white hover:bg-red-700',
    gris: 'bg-gray-200 text-gray-700 hover:bg-gray-300',
};

const jetonCsrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/*
 * Heure du Cameroun, forcée par le fuseau plutôt que reprise du système.
 * Le boîtier d'une télé est très souvent réglé sur un autre fuseau, ou pas réglé
 * du tout : afficher son heure locale donnerait un horaire faux sur un écran
 * censé faire référence pour l'équipe.
 */
const FUSEAU_CAMEROUN = 'Africa/Douala';

const heureCameroun = new Intl.DateTimeFormat('fr-FR', {
    timeZone: FUSEAU_CAMEROUN,
    hour: '2-digit',
    minute: '2-digit',
});

const secondesCameroun = new Intl.DateTimeFormat('fr-FR', {
    timeZone: FUSEAU_CAMEROUN,
    second: '2-digit',
});

const dateCameroun = new Intl.DateTimeFormat('fr-FR', {
    timeZone: FUSEAU_CAMEROUN,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function Horloge() {
    const [heure, setHeure] = useState(() => new Date());

    useEffect(() => {
        const timer = setInterval(() => setHeure(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    return (
        <div className="text-right leading-none">
            <p className="font-mono text-4xl font-bold tabular-nums text-gray-900">
                {heureCameroun.format(heure)}
                <span className="ml-1 text-xl text-gray-400">{secondesCameroun.format(heure)}</span>
            </p>
            <p className="mt-1 text-sm font-semibold capitalize text-gray-600">
                {dateCameroun.format(heure)}
            </p>
            <p className="text-[11px] font-bold uppercase tracking-wider text-gray-400">
                Heure du Cameroun
            </p>
        </div>
    );
}

function Compteur({ label, valeur, accent = 'text-gray-900' }) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white px-5 py-3 shadow-sm">
            <p className="text-[11px] font-bold uppercase tracking-wider text-gray-500">{label}</p>
            <p className={`mt-1 text-3xl font-extrabold tabular-nums ${accent}`}>{valeur}</p>
        </div>
    );
}

function DetailCommande({ commande, onClose, onChangerStatut, enCours }) {
    const statut = STATUTS[commande.status] ?? { label: commande.status, classe: 'bg-gray-100 text-gray-700 ring-gray-300' };
    const totalArticles = commande.items.reduce((somme, article) => somme + article.quantity, 0);

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={onClose} />

            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

                    <div className="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 px-6 py-4">
                        <div>
                            <h2 className="font-mono text-xl font-bold text-gray-900">{commande.ref ?? `#${commande.id}`}</h2>
                            <p className="mt-0.5 text-sm text-gray-500">{commande.created_full}</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className={`rounded-full px-3 py-1 text-sm font-bold ring-1 ring-inset ${statut.classe}`}>
                                {statut.label}
                            </span>
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                            >
                                <span className="sr-only">Fermer</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="rounded-xl border border-gray-200 p-4">
                                <p className="text-xs font-bold uppercase tracking-wider text-gray-500">Client</p>
                                <p className="mt-2 text-lg font-bold text-gray-900">{commande.customer ?? '—'}</p>
                                {commande.phone && <p className="mt-1 font-mono text-gray-600">{commande.phone}</p>}
                                {commande.whatsapp && <p className="font-mono text-sm text-gray-500">WhatsApp : {commande.whatsapp}</p>}
                                {commande.email && <p className="truncate text-sm text-gray-500">{commande.email}</p>}
                            </div>

                            <div className="rounded-xl border border-gray-200 p-4">
                                <p className="text-xs font-bold uppercase tracking-wider text-gray-500">Livraison</p>
                                <p className="mt-2 text-gray-900">{commande.address ?? '—'}</p>
                                <p className="mt-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                                    Paiement : {commande.payment_method ?? '—'}
                                </p>
                            </div>
                        </div>

                        {commande.agent && (
                            <div className="mt-5 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                                <p className="text-xs font-bold uppercase tracking-wider text-indigo-700">Agent assigné</p>
                                <p className="mt-2 text-lg font-bold text-gray-900">{commande.agent.name}</p>
                                {commande.agent.phone && <p className="font-mono text-gray-600">{commande.agent.phone}</p>}
                                {commande.agent.whatsapp && <p className="font-mono text-sm text-gray-500">WhatsApp : {commande.agent.whatsapp}</p>}
                            </div>
                        )}

                        <div className="mt-5">
                            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                                Panier — {commande.items_count} article{commande.items_count > 1 ? 's' : ''} ({totalArticles} unité{totalArticles > 1 ? 's' : ''})
                            </p>

                            {commande.items.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-gray-400">
                                    Aucun article rattaché à cette commande
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-xl border border-gray-200">
                                    <table className="w-full text-left">
                                        <thead className="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                                            <tr>
                                                <th className="px-4 py-2.5">Article</th>
                                                <th className="px-4 py-2.5 text-center">Qté</th>
                                                <th className="px-4 py-2.5 text-right">Prix unitaire</th>
                                                <th className="px-4 py-2.5 text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {commande.items.map((article, index) => (
                                                <tr key={index}>
                                                    <td className="px-4 py-3 font-semibold text-gray-900">{article.name}</td>
                                                    <td className="px-4 py-3 text-center font-bold tabular-nums text-gray-700">×{article.quantity}</td>
                                                    <td className="px-4 py-3 text-right tabular-nums text-gray-600">
                                                        {article.unit_price ? `${formatMontant(article.unit_price)} F` : '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-bold tabular-nums text-gray-900">
                                                        {article.amount ? `${formatMontant(article.amount)} F` : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex shrink-0 flex-wrap items-center justify-between gap-4 border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">Total commande</p>
                            <p className="text-3xl font-extrabold tabular-nums text-gray-900">
                                {formatMontant(commande.price)} <span className="text-base font-bold text-gray-400">F</span>
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {(ACTIONS[commande.status] ?? []).map((action) => (
                                <button
                                    key={action.statut}
                                    type="button"
                                    onClick={() => onChangerStatut(commande.id, action.statut)}
                                    disabled={enCours}
                                    className={`rounded-xl px-5 py-3 text-base font-bold transition-all duration-200 active:scale-95 disabled:cursor-wait disabled:opacity-40 ${VARIANTES[action.variante]}`}
                                >
                                    {action.libelle}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Board({ initial }) {
    const [donnees, setDonnees] = useState(initial);
    const [page, setPage] = useState(initial.pagination?.current_page ?? 1);
    const [sonActif, setSonActif] = useState(false);
    const [silence, setSilence] = useState(false);
    const [enLigne, setEnLigne] = useState(true);
    const [nouvelles, setNouvelles] = useState(() => new Set());
    const [detailId, setDetailId] = useState(null);
    const [veilleBloquee, setVeilleBloquee] = useState(false);
    // Commandes dont le changement de statut est en cours : leurs boutons sont
    // neutralisés le temps de l'aller-retour, pour éviter le double clic.
    const [enCours, setEnCours] = useState(() => new Set());

    const audioRef = useRef(null);
    // Références plutôt qu'états : lues dans les intervalles, elles ne doivent pas
    // en déclencher la recréation à chaque rendu.
    // On suit l'identifiant le plus élevé toutes pages confondues : se fonder sur
    // la page affichée raterait toute nouvelle commande dès qu'on quitte la page 1.
    const dernierIdRef = useRef(initial.latest_id ?? 0);
    const pageRef = useRef(initial.pagination?.current_page ?? 1);
    const sonActifRef = useRef(false);
    const silenceRef = useRef(false);

    const jouerAlerte = useCallback(() => {
        if (!sonActifRef.current || silenceRef.current || !audioRef.current) return;

        audioRef.current.currentTime = 0;
        audioRef.current.play().catch(() => {
            sonActifRef.current = false;
            setSonActif(false);
        });
    }, []);

    /*
     * Débloque l'audio et mémorise le choix.
     *
     * Le navigateur exige un geste utilisateur par chargement de page avant
     * d'autoriser un son : c'est une politique navigateur, aucun code ne la
     * contourne. On mémorise donc la préférence et on retente silencieusement à
     * chaque ouverture ; quand la tentative échoue, n'importe quel clic sur la
     * page suffit à débloquer (voir l'écouteur global plus bas), au lieu
     * d'imposer de viser le bouton.
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

    // Réactivation automatique à l'ouverture, puis repli sur le premier geste venu.
    useEffect(() => {
        let prefere = false;
        try {
            prefere = localStorage.getItem(CLE_SON) === '1';
        } catch {
            prefere = false;
        }

        if (!prefere) return undefined;

        let annule = false;
        let surGeste = null;

        const nettoyer = () => {
            if (!surGeste) return;
            ['pointerdown', 'keydown', 'touchstart'].forEach((evt) =>
                window.removeEventListener(evt, surGeste)
            );
            surGeste = null;
        };

        activerSon().then((reussi) => {
            if (annule || reussi) return;

            surGeste = () => {
                activerSon().then((ok) => ok && nettoyer());
            };
            ['pointerdown', 'keydown', 'touchstart'].forEach((evt) =>
                window.addEventListener(evt, surGeste, { passive: true })
            );
        });

        return () => {
            annule = true;
            nettoyer();
        };
    }, [activerSon]);

    const basculerSilence = () => {
        const nouveau = !silenceRef.current;
        silenceRef.current = nouveau;
        setSilence(nouveau);
    };

    const passerEnPleinEcran = () => {
        if (document.fullscreenElement) {
            document.exitFullscreen?.();
        } else {
            document.documentElement.requestFullscreen?.();
        }
    };

    const commandes = useMemo(() => donnees.orders ?? [], [donnees.orders]);
    const pagination = donnees.pagination ?? { current_page: 1, last_page: 1, total: commandes.length };

    /*
     * Nombre de commandes en attente compté par le serveur, toutes pages confondues.
     * Le compter sur la page affichée ferait taire la sonnerie dès qu'on navigue
     * vers une page où il n'y a que des commandes livrées.
     */
    const nbEnAttente = donnees.stats?.en_attente ?? 0;

    // Le détail suit les rafraîchissements : la commande ouverte est relue dans
    // les données fraîches, sinon le panneau afficherait un statut périmé.
    const commandeDetail = useMemo(
        () => commandes.find((o) => o.id === detailId) ?? null,
        [commandes, detailId]
    );

    // Sonnerie répétée tant qu'une commande n'est pas prise en charge. Le rappel
    // est indépendant de l'arrivée d'une nouvelle commande : une commande oubliée
    // continue de sonner même si plus rien n'arrive.
    useEffect(() => {
        if (nbEnAttente === 0) return undefined;

        const timer = setInterval(jouerAlerte, RAPPEL_SONORE_MS);
        return () => clearInterval(timer);
    }, [nbEnAttente, jouerAlerte]);

    // Le silence se lève dès qu'il n'y a plus rien en attente, pour ne pas rester
    // muet à l'insu de tout le monde le jour suivant.
    useEffect(() => {
        if (silenceRef.current && nbEnAttente === 0) {
            silenceRef.current = false;
            setSilence(false);
        }
    }, [nbEnAttente]);

    useEffect(() => {
        let annule = false;

        const recuperer = async () => {
            try {
                const reponse = await fetch(`/commandes/flux?page=${pageRef.current}`, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!reponse.ok) throw new Error(String(reponse.status));

                const charge = await reponse.json();
                if (annule) return;

                setEnLigne(true);
                setDonnees(charge);

                // latest_id est calculé sur toute la table, pas sur la page affichée :
                // une commande qui arrive pendant qu'on consulte la page 3 déclenche
                // quand même la sonnerie.
                const idMax = charge.latest_id ?? 0;

                if (idMax > dernierIdRef.current) {
                    const fraiches = charge.orders
                        .filter((o) => o.id > dernierIdRef.current)
                        .map((o) => o.id);

                    dernierIdRef.current = idMax;
                    setNouvelles((precedent) => new Set([...precedent, ...fraiches]));

                    // Une nouvelle commande lève le silence : on ne veut pas
                    // qu'un « couper le son » cliqué plus tôt masque la suivante.
                    silenceRef.current = false;
                    setSilence(false);

                    jouerAlerte();
                }
            } catch {
                if (!annule) setEnLigne(false);
            }
        };

        // Récupération immédiate au changement de page, sans attendre le prochain
        // cycle : sinon la navigation semble ne rien faire pendant cinq secondes.
        recuperer();

        const timer = setInterval(recuperer, INTERVALLE_MS);
        return () => {
            annule = true;
            clearInterval(timer);
        };
    }, [jouerAlerte, page]);

    /*
     * Changement de statut. La réponse contient déjà le mur à jour : on l'applique
     * directement, sans attendre le prochain cycle de cinq secondes, sinon le
     * bouton semble ne rien faire.
     */
    const changerStatut = async (id, statut) => {
        setEnCours((precedent) => new Set([...precedent, id]));

        try {
            const reponse = await fetch(`/commandes/${id}/statut`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': jetonCsrf(),
                },
                body: JSON.stringify({ status: statut }),
            });

            if (!reponse.ok) throw new Error(String(reponse.status));

            const charge = await reponse.json();
            setDonnees(charge);
            setEnLigne(true);

            // La commande traitée cesse d'être signalée comme nouvelle.
            setNouvelles((precedent) => {
                const copie = new Set(precedent);
                copie.delete(id);
                return copie;
            });
        } catch {
            setEnLigne(false);
        } finally {
            setEnCours((precedent) => {
                const copie = new Set(precedent);
                copie.delete(id);
                return copie;
            });
        }
    };

    const allerPage = (cible) => {
        const borne = Math.min(Math.max(1, cible), pagination.last_page || 1);
        pageRef.current = borne;
        setPage(borne);
        setDetailId(null);
    };

    /*
     * Empêche la mise en veille de l'écran.
     *
     * L'API Wake Lock relâche le verrou dès que l'onglet passe en arrière-plan ou
     * que l'appareil se verrouille : sans la reprise sur « visibilitychange »,
     * l'écran s'éteint après le premier basculement et ne se rallume plus.
     *
     * Elle exige une origine sécurisée : effective sur https://pouletafc.com et
     * sur localhost, ignorée ailleurs. Les navigateurs qui ne l'implémentent pas
     * (Safari ancien, WebView de certaines télés) ne sont pas couverts — d'où
     * l'indicateur à l'écran, pour qu'on ne croie pas la veille désactivée alors
     * qu'elle ne l'est pas.
     */
    useEffect(() => {
        let verrou = null;
        let abandonne = false;

        const demander = async () => {
            if (!('wakeLock' in navigator)) {
                setVeilleBloquee(false);
                return;
            }

            try {
                verrou = await navigator.wakeLock.request('screen');
                if (abandonne) {
                    verrou.release().catch(() => {});
                    return;
                }
                setVeilleBloquee(true);
                verrou.addEventListener('release', () => setVeilleBloquee(false));
            } catch {
                setVeilleBloquee(false);
            }
        };

        demander();

        const surVisibilite = () => {
            if (document.visibilityState === 'visible') demander();
        };

        document.addEventListener('visibilitychange', surVisibilite);

        return () => {
            abandonne = true;
            document.removeEventListener('visibilitychange', surVisibilite);
            verrou?.release?.().catch(() => {});
        };
    }, []);

    // Échap ferme le détail : sur un écran mural, la souris n'est pas toujours à portée.
    useEffect(() => {
        const surTouche = (e) => {
            if (e.key === 'Escape') setDetailId(null);
        };
        window.addEventListener('keydown', surTouche);
        return () => window.removeEventListener('keydown', surTouche);
    }, []);

    const stats = donnees.stats;

    return (
        <>
            <Head title="Mur des commandes" />

            <style>{`
                @keyframes ligne-alerte {
                    0%, 100% { background-color: rgb(254 249 231); }
                    50% { background-color: rgb(253 240 190); }
                }
                .ligne-alerte { animation: ligne-alerte 1.8s ease-in-out infinite; }
            `}</style>

            <div className="flex min-h-screen flex-col bg-gray-100 px-6 py-5 text-gray-900">

                <header className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-6 py-4 shadow-sm">
                    <div className="flex items-center gap-4">
                        <img src="/images/logo.png" alt="" className="h-14 w-auto" />
                        <div>
                            <h1 className="text-2xl font-extrabold tracking-tight xl:text-3xl">Commandes en direct</h1>
                            <div className="mt-1 flex items-center gap-2 text-sm">
                                <span className={`inline-block h-2.5 w-2.5 rounded-full ${enLigne ? 'animate-pulse bg-emerald-500' : 'bg-red-500'}`} />
                                <span className={enLigne ? 'font-semibold text-emerald-700' : 'font-bold text-red-600'}>
                                    {enLigne ? `Connecté · ${donnees.server_time}` : 'Connexion perdue — données figées'}
                                </span>

                                {veilleBloquee && (
                                    <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500" title="La mise en veille de l'écran est bloquée">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-3.5 w-3.5">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                        </svg>
                                        Veille bloquée
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Compteur label="Aujourd'hui" valeur={stats.du_jour} />
                        <Compteur label="À traiter" valeur={stats.actives} accent="text-amber-600" />
                        <Compteur label="Livrées" valeur={stats.livrees} accent="text-emerald-600" />
                        <Compteur label="CA du jour" valeur={`${formatMontant(stats.ca_jour)} F`} />
                        <Horloge />
                    </div>
                </header>

                {!sonActif ? (
                    <button
                        type="button"
                        onClick={activerSon}
                        className="mt-4 flex w-full items-center justify-center gap-3 rounded-2xl bg-amber-400 px-6 py-4 text-lg font-black text-gray-900 shadow-sm transition-transform hover:bg-amber-300 active:scale-[0.99]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-6 w-6">
                            <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.108 0-2.008.9-2.008 2.01v5c0 1.11.9 2.01 2.008 2.01H6.44l4.5 4.5c.944.945 2.56.276 2.56-1.06V4.06zM18.584 5.106a.75.75 0 011.06 0c3.808 3.807 3.808 9.98 0 13.788a.75.75 0 11-1.06-1.06 8.25 8.25 0 000-11.668.75.75 0 010-1.06z" />
                        </svg>
                        Cliquez n'importe où pour activer la sonnerie
                        <span className="text-sm font-semibold opacity-70">— le choix est mémorisé pour les prochaines ouvertures</span>
                    </button>
                ) : nbEnAttente > 0 ? (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-300 bg-amber-50 px-6 py-4">
                        <p className="text-lg font-bold text-amber-800">
                            {nbEnAttente} commande{nbEnAttente > 1 ? 's' : ''} en attente de prise en charge
                            {!silence && <span className="ml-2 text-sm font-semibold text-amber-600">— rappel sonore toutes les 20 s</span>}
                        </p>
                        <button
                            type="button"
                            onClick={basculerSilence}
                            className={`rounded-xl px-5 py-2 text-sm font-bold transition-colors ${
                                silence
                                    ? 'bg-gray-200 text-gray-600 hover:bg-gray-300'
                                    : 'bg-amber-500 text-white hover:bg-amber-600'
                            }`}
                        >
                            {silence ? 'Sonnerie coupée — réactiver' : 'Couper la sonnerie'}
                        </button>
                    </div>
                ) : null}

                <main className="mt-4 flex-1">
                    {commandes.length === 0 ? (
                        <div className="flex min-h-[60vh] flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white text-center">
                            <p className="text-3xl font-bold text-gray-300">Aucune commande pour le moment</p>
                            <p className="mt-2 text-gray-400">L'écran s'actualise automatiquement</p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-gray-200 bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                                        <th className="px-4 py-3">Heure</th>
                                        <th className="px-4 py-3">Référence</th>
                                        <th className="px-4 py-3">Client</th>
                                        <th className="px-4 py-3">Adresse</th>
                                        <th className="px-4 py-3 text-center">Détail</th>
                                        <th className="px-4 py-3 text-right">Montant</th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {commandes.map((commande) => {
                                        const statut = STATUTS[commande.status] ?? {
                                            label: commande.status,
                                            classe: 'bg-gray-100 text-gray-700 ring-gray-300',
                                        };
                                        const estNouvelle = nouvelles.has(commande.id);
                                        const attend = STATUTS_EN_ATTENTE.includes(commande.status);

                                        return (
                                            <tr
                                                key={commande.id}
                                                className={`text-lg transition-colors ${
                                                    estNouvelle || attend ? 'ligne-alerte' : 'hover:bg-gray-50'
                                                }`}
                                            >
                                                <td className="whitespace-nowrap px-4 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-mono text-xl font-bold tabular-nums text-gray-900">
                                                            {commande.created_label}
                                                        </span>
                                                        {estNouvelle && (
                                                            <span className="rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-black uppercase text-white">
                                                                Nouveau
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-0.5 text-xs font-semibold text-gray-500">
                                                        {commande.created_day}
                                                    </p>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-4 font-mono text-base text-gray-500">
                                                    {commande.ref ?? `#${commande.id}`}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <p className="font-semibold text-gray-900">{commande.customer ?? '—'}</p>
                                                    {commande.phone && (
                                                        <p className="font-mono text-sm text-gray-500">{commande.phone}</p>
                                                    )}
                                                </td>
                                                <td className="max-w-xs px-4 py-4 text-base text-gray-600">
                                                    {commande.address ?? '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-4 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailId(commande.id)}
                                                        title="Voir le panier et les coordonnées"
                                                        className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-gray-500 transition-colors hover:bg-brand-50 hover:text-brand-700"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-6 w-6">
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        {commande.items_count > 0 && (
                                                            <span className="text-sm font-bold tabular-nums">{commande.items_count}</span>
                                                        )}
                                                    </button>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-4 text-right text-2xl font-extrabold tabular-nums text-gray-900">
                                                    {formatMontant(commande.price)}
                                                    <span className="ml-1 text-sm font-bold text-gray-400">F</span>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-4">
                                                    <span className={`rounded-full px-3 py-1 text-sm font-bold ring-1 ring-inset ${statut.classe}`}>
                                                        {statut.label}
                                                    </span>
                                                    <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                        {commande.payment_method ?? '—'}
                                                    </p>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-4">
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {(ACTIONS[commande.status] ?? []).map((action) => (
                                                            <button
                                                                key={action.statut}
                                                                type="button"
                                                                onClick={() => changerStatut(commande.id, action.statut)}
                                                                disabled={enCours.has(commande.id)}
                                                                className={`rounded-lg px-3 py-2 text-sm font-bold transition-all duration-200 active:scale-95 disabled:cursor-wait disabled:opacity-40 ${VARIANTES[action.variante]}`}
                                                            >
                                                                {action.libelle}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            {pagination.last_page > 1 && (
                                <div className="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 bg-gray-50 px-4 py-3">
                                    <p className="text-sm font-semibold text-gray-600">
                                        {pagination.from}–{pagination.to} sur {pagination.total} commandes
                                        {page > 1 && (
                                            <span className="ml-2 font-normal text-gray-400">
                                                — les nouvelles arrivent en page 1
                                            </span>
                                        )}
                                    </p>

                                    <div className="flex items-center gap-1">
                                        <button
                                            type="button"
                                            onClick={() => allerPage(1)}
                                            disabled={page === 1}
                                            className="rounded-lg px-3 py-2 text-sm font-bold text-gray-600 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-30"
                                        >
                                            ««
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => allerPage(page - 1)}
                                            disabled={page === 1}
                                            className="rounded-lg px-4 py-2 text-sm font-bold text-gray-600 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-30"
                                        >
                                            Précédent
                                        </button>

                                        {/* Fenêtre de 5 pages autour de la page courante : au-delà,
                                            la barre déborde dès qu'il y a beaucoup de commandes. */}
                                        {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
                                            .filter((n) => Math.abs(n - page) <= 2)
                                            .map((n) => (
                                                <button
                                                    key={n}
                                                    type="button"
                                                    onClick={() => allerPage(n)}
                                                    className={`min-w-[2.5rem] rounded-lg px-3 py-2 text-sm font-bold transition-colors ${
                                                        n === page
                                                            ? 'bg-brand-600 text-white shadow-sm'
                                                            : 'text-gray-600 hover:bg-white'
                                                    }`}
                                                >
                                                    {n}
                                                </button>
                                            ))}

                                        <button
                                            type="button"
                                            onClick={() => allerPage(page + 1)}
                                            disabled={page >= pagination.last_page}
                                            className="rounded-lg px-4 py-2 text-sm font-bold text-gray-600 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-30"
                                        >
                                            Suivant
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => allerPage(pagination.last_page)}
                                            disabled={page >= pagination.last_page}
                                            className="rounded-lg px-3 py-2 text-sm font-bold text-gray-600 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-30"
                                        >
                                            »»
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </main>

                <button
                    type="button"
                    onClick={passerEnPleinEcran}
                    className="fixed bottom-5 right-5 rounded-full border border-gray-200 bg-white p-3 text-gray-400 shadow-md transition-colors hover:text-gray-700"
                    title="Plein écran"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                    </svg>
                </button>

                <audio ref={audioRef} preload="auto">
                    <source src="/sounds/notification.mp3" type="audio/mpeg" />
                    <source src="/sounds/notification.wav" type="audio/wav" />
                </audio>

                {commandeDetail && (
                    <DetailCommande
                        commande={commandeDetail}
                        onClose={() => setDetailId(null)}
                        onChangerStatut={changerStatut}
                        enCours={enCours.has(commandeDetail.id)}
                    />
                )}
            </div>
        </>
    );
}
