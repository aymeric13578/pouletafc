import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/*
 * Socle commun aux cartes clando et livraisons.
 *
 * Les deux écrans font le même travail : afficher des points qui bougent,
 * rafraîchis en boucle, avec des filtres et une attribution à un agent. Tout ce
 * qui ne diffère pas — pilotage de Leaflet, synchronisation des marqueurs,
 * alerte sonore — vit ici, pour qu'une correction profite aux deux plutôt que
 * d'être appliquée une fois sur deux.
 */

// Douala, tant qu'aucun point n'est disponible.
export const CENTRE_DEFAUT = [4.0511, 9.7679];
export const ZOOM_DEFAUT = 12;

export const formatMontant = (valeur) => new Intl.NumberFormat('fr-FR').format(valeur ?? 0);

export const echapper = (texte) =>
    String(texte ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]),
    );

/*
 * Marqueurs dessinés en HTML plutôt qu'en images.
 *
 * Les icônes Leaflet par défaut pointent vers des fichiers résolus relativement
 * au CSS ; avec Vite ces chemins cassent et les marqueurs disparaissent. Des
 * divIcon évitent le problème et permettent de colorer un point selon son statut
 * sans embarquer une image par couleur.
 */
const enveloppe = (couleur, svg, pulse) =>
    `<div class="relative flex h-7 w-7 items-center justify-center">
        ${pulse ? `<span class="absolute inline-flex h-7 w-7 animate-ping rounded-full opacity-60" style="background:${couleur}"></span>` : ''}
        <span class="relative inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-white text-white shadow-lg" style="background:${couleur}">
            ${svg}
        </span>
    </div>`;

const SVG = {
    agent: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-4 w-4"><path d="M5 17h2m10 0h2M6 17a2 2 0 104 0 2 2 0 10-4 0m8 0a2 2 0 104 0 2 2 0 10-4 0M4 13l1.5-5h9l3 5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    client: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-4 w-4"><path d="M16 20v-1a4 4 0 00-8 0v1M12 11a3 3 0 100-6 3 3 0 000 6z" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    lieu: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-3.5 w-3.5"><path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.4"/></svg>',
    boutique:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-3.5 w-3.5"><path d="M4 9h16l-1 11H5L4 9zm2-3h12l1 3H5l1-3z" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};

export const iconeAgent = (frais) =>
    L.divIcon({
        className: '',
        html: enveloppe(frais ? '#10b981' : '#94a3b8', SVG.agent, frais),
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });

export const iconePoint = (couleur, forme = 'client', pulse = false) =>
    L.divIcon({
        className: '',
        html: enveloppe(couleur, SVG[forme] ?? SVG.client, pulse),
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });

/**
 * Valeur retardée, pour que la saisie ne relance pas le filtrage à chaque touche.
 *
 * Le filtrage lui-même est mémoïsé et rapide, mais il entraîne la synchronisation
 * des marqueurs Leaflet : sans ce délai, taper « bonaberi » reconstruit huit fois
 * de suite les calques de la carte.
 */
export function useDebounce(valeur, delai = 150) {
    const [retardee, setRetardee] = useState(valeur);

    useEffect(() => {
        const timer = setTimeout(() => setRetardee(valeur), delai);
        return () => clearTimeout(timer);
    }, [valeur, delai]);

    return retardee;
}

/**
 * Crée la carte une fois et la garde hors du cycle de rendu React.
 *
 * La reconstruire à chaque rendu la ferait clignoter à chaque rafraîchissement et
 * perdrait le zoom, le déplacement et la fiche ouverte.
 */
export function useCarteLeaflet(conteneurRef, { surDeplacement } = {}) {
    const carteRef = useRef(null);

    useEffect(() => {
        if (carteRef.current || !conteneurRef.current) return undefined;

        const carte = L.map(conteneurRef.current, {
            center: CENTRE_DEFAUT,
            zoom: ZOOM_DEFAUT,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(carte);

        // Déplacer la carte à la main coupe le recadrage automatique, sinon le
        // cycle suivant ramènerait la vue et la carte serait inexplorable.
        if (surDeplacement) carte.on('dragstart', surDeplacement);

        carteRef.current = carte;

        return () => {
            carte.remove();
            carteRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return carteRef;
}

/**
 * Synchronise un calque de marqueurs avec les données.
 *
 * Les marqueurs existants sont déplacés, pas recréés : les recréer à chaque cycle
 * ferait disparaître puis réapparaître les points toutes les quatre secondes et
 * fermerait la fiche ouverte.
 */
export function useSynchronisation(carteRef) {
    return useCallback(
        (registre, elements, fabriquer, majExistant) => {
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

            // Ce qui a quitté le flux quitte la carte : une course terminée ne
            // doit pas laisser un marqueur fantôme.
            registre.current.forEach((marqueur, id) => {
                if (!vus.has(id)) {
                    carte.removeLayer(marqueur);
                    registre.current.delete(id);
                }
            });
        },
        [carteRef],
    );
}

/**
 * Alerte sonore, avec la même politique que le mur des commandes : le navigateur
 * exige un geste utilisateur par chargement de page, on mémorise la préférence et
 * on retente à l'ouverture, avec repli sur le premier clic venu.
 */
export function useAlerteSonore(cleStockage) {
    const audioRef = useRef(null);
    const sonActifRef = useRef(false);
    const silenceRef = useRef(false);
    const [sonActif, setSonActif] = useState(false);
    const [silence, setSilence] = useState(false);

    const jouer = useCallback(() => {
        if (!sonActifRef.current || silenceRef.current || !audioRef.current) return;

        audioRef.current.currentTime = 0;
        audioRef.current.play().catch(() => {
            sonActifRef.current = false;
            setSonActif(false);
        });
    }, []);

    const activer = useCallback(async () => {
        if (!audioRef.current) return false;

        try {
            await audioRef.current.play();
            audioRef.current.pause();
            audioRef.current.currentTime = 0;
            sonActifRef.current = true;
            setSonActif(true);
            try {
                localStorage.setItem(cleStockage, '1');
            } catch {
                // Navigation privée : on continue sans mémoriser.
            }
            return true;
        } catch {
            sonActifRef.current = false;
            setSonActif(false);
            return false;
        }
    }, [cleStockage]);

    useEffect(() => {
        let prefere = false;
        try {
            prefere = localStorage.getItem(cleStockage) === '1';
        } catch {
            prefere = false;
        }

        if (!prefere) return undefined;

        let annule = false;
        activer().then((ok) => {
            if (annule || ok) return;

            const surGeste = () => {
                activer();
                document.removeEventListener('pointerdown', surGeste);
            };
            document.addEventListener('pointerdown', surGeste);
        });

        return () => {
            annule = true;
        };
    }, [activer, cleStockage]);

    const couper = useCallback(() => {
        silenceRef.current = true;
        setSilence(true);
    }, []);

    const leverSilence = useCallback(() => {
        silenceRef.current = false;
        setSilence(false);
    }, []);

    return { audioRef, sonActif, silence, jouer, activer, couper, leverSilence };
}

export function Compteur({ libelle, valeur, ton = 'slate' }) {
    const tons = {
        sky: 'bg-sky-50 text-sky-700 ring-sky-200',
        red: 'bg-red-50 text-red-700 ring-red-200',
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        amber: 'bg-amber-50 text-amber-700 ring-amber-200',
        slate: 'bg-slate-50 text-slate-700 ring-slate-200',
    };

    return (
        <span className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ${tons[ton] ?? tons.slate}`}>
            {libelle} <span className="ml-1 text-sm font-bold tabular-nums">{valeur}</span>
        </span>
    );
}

/**
 * Choix d'un agent, ouvert depuis une course ou une commande non attribuée.
 *
 * Les agents occupés restent listés mais grisés : les masquer ferait croire à un
 * opérateur qu'un agent n'existe pas, alors qu'il est simplement en course.
 */
export function ChoixAgent({ agents, enCours, surChoix, surAnnuler }) {
    const [recherche, setRecherche] = useState('');
    const terme = useDebounce(recherche.trim().toLowerCase(), 120);

    const liste = useMemo(() => {
        if (!terme) return agents;
        return agents.filter((a) =>
            [a.name, a.phone, a.matricule].filter(Boolean).some((v) => String(v).toLowerCase().includes(terme)),
        );
    }, [agents, terme]);

    return (
        <div className="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div className="flex items-center justify-between">
                <p className="text-xs font-semibold text-slate-700">Attribuer à un agent</p>
                <button
                    type="button"
                    onClick={surAnnuler}
                    className="text-xs font-semibold text-slate-500 hover:text-slate-800"
                >
                    Fermer
                </button>
            </div>

            <input
                type="search"
                value={recherche}
                onChange={(e) => setRecherche(e.target.value)}
                placeholder="Nom, téléphone, matricule"
                className="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500"
            />

            <div className="max-h-52 space-y-1 overflow-y-auto">
                {liste.length === 0 ? (
                    <p className="py-3 text-center text-xs text-slate-400">Aucun agent</p>
                ) : (
                    liste.map((a) => (
                        <button
                            key={a.id_user}
                            type="button"
                            disabled={enCours}
                            onClick={() => surChoix(a)}
                            className="flex w-full items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-left ring-1 ring-slate-200 transition hover:ring-slate-400 disabled:opacity-50"
                        >
                            <span className="min-w-0">
                                <span className="block truncate text-xs font-semibold text-slate-900">{a.name}</span>
                                <span className="block truncate text-[11px] text-slate-500">
                                    {a.phone}
                                    {a.matricule ? ` · ${a.matricule}` : ''}
                                </span>
                            </span>
                            <span className="flex shrink-0 flex-col items-end gap-0.5">
                                <span
                                    className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                                        a.en_service ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {a.en_service ? 'En service' : 'Hors service'}
                                </span>
                                <span
                                    className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                                        a.libre ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'
                                    }`}
                                >
                                    {a.libre ? 'Libre' : 'Occupé'}
                                </span>
                            </span>
                        </button>
                    ))
                )}
            </div>
        </div>
    );
}
