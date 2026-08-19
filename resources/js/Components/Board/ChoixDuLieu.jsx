import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * Choisir où livrer : un lieu déjà connu, ou un nouveau.
 *
 * L'adresse était figée à la création de la commande et plus personne ne pouvait
 * la corriger. Un quartier mal compris au téléphone obligeait à annuler et à
 * ressaisir, en perdant l'historique et l'agent déjà attribué.
 *
 * La recherche est là parce que la liste dépasse déjà cent-quatre-vingt-dix
 * lieux : un menu déroulant brut serait inutilisable au comptoir, un client au
 * bout du fil.
 *
 * Et la création est ici, et non renvoyée à l'écran des lieux : le comptoir a
 * souvent la bonne adresse sans qu'elle figure dans la liste, et l'obliger à
 * changer d'écran puis à revenir, c'est l'assurance qu'il livrera au jugé.
 */
export default function ChoixDuLieu({ ouvert, titre, lieux = [], actuel, enCours = false, onFermer, onValider }) {
    const [recherche, setRecherche] = useState('');
    const [nouveau, setNouveau] = useState(null);
    const champ = useRef(null);

    useEffect(() => {
        if (ouvert) {
            setRecherche('');
            setNouveau(null);
            setTimeout(() => champ.current?.focus(), 60);
        }
    }, [ouvert]);

    useEffect(() => {
        if (!ouvert) return undefined;

        const auClavier = (evenement) => {
            if (evenement.key === 'Escape') onFermer();
        };

        window.addEventListener('keydown', auClavier);
        return () => window.removeEventListener('keydown', auClavier);
    }, [ouvert, onFermer]);

    const filtres = useMemo(() => {
        const terme = recherche.trim().toLowerCase();

        if (terme === '') return lieux.slice(0, 40);

        return lieux
            .filter((lieu) => lieu.libelle.toLowerCase().includes(terme))
            .slice(0, 40);
    }, [lieux, recherche]);

    if (!ouvert) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div className="flex max-h-[85vh] w-full max-w-xl flex-col rounded-2xl bg-white p-5 shadow-2xl">
                <h2 className="text-lg font-bold text-gray-900">{titre}</h2>
                {actuel && (
                    <p className="mt-1 text-sm text-gray-500">
                        Actuellement : <span className="font-semibold text-gray-700">{actuel}</span>
                    </p>
                )}

                {nouveau === null ? (
                    <>
                        <input
                            ref={champ}
                            type="search"
                            value={recherche}
                            onChange={(evenement) => setRecherche(evenement.target.value)}
                            placeholder="Chercher un lieu ou un quartier…"
                            className="mt-4 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0"
                        />

                        <div className="mt-3 min-h-0 flex-1 overflow-y-auto rounded-xl border border-gray-100">
                            {filtres.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-gray-500">
                                    Aucun lieu ne correspond. Créez-le juste en dessous.
                                </p>
                            ) : (
                                filtres.map((lieu) => (
                                    <button
                                        key={lieu.id}
                                        type="button"
                                        disabled={enCours}
                                        onClick={() => onValider({ location_id: lieu.id })}
                                        className="flex w-full items-center justify-between gap-3 border-b border-gray-50 px-4 py-2.5 text-left transition-colors hover:bg-gray-50 disabled:opacity-50"
                                    >
                                        <span className="text-sm font-semibold text-gray-800">{lieu.libelle}</span>
                                        {/* Un lieu sans coordonnées nomme la livraison mais ne se
                                            place pas sur la carte : le comptoir doit le savoir. */}
                                        {!lieu.localise && (
                                            <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">
                                                sans repère
                                            </span>
                                        )}
                                    </button>
                                ))
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => setNouveau({ name: recherche.trim(), quarter_name: '' })}
                            className="mt-3 rounded-xl border border-dashed border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            + Enregistrer un nouveau lieu
                        </button>
                    </>
                ) : (
                    <div className="mt-4 space-y-3">
                        <div>
                            <label className="text-xs font-bold uppercase tracking-wider text-gray-400">Nom du lieu</label>
                            <input
                                type="text"
                                value={nouveau.name}
                                autoFocus
                                onChange={(evenement) => setNouveau({ ...nouveau, name: evenement.target.value })}
                                placeholder="Carrefour Total, École publique…"
                                className="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-bold uppercase tracking-wider text-gray-400">Quartier</label>
                            <input
                                type="text"
                                value={nouveau.quarter_name}
                                onChange={(evenement) => setNouveau({ ...nouveau, quarter_name: evenement.target.value })}
                                placeholder="Barmari, Plateau…"
                                className="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0"
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Le quartier est créé s'il n'existe pas encore. Le lieu rejoint la liste commune : il servira
                                aux prochaines commandes et aux agents.
                            </p>
                        </div>

                        <div className="flex items-center justify-between gap-2 pt-1">
                            <button
                                type="button"
                                onClick={() => setNouveau(null)}
                                className="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                            >
                                ← Revenir à la liste
                            </button>
                            <button
                                type="button"
                                disabled={enCours || nouveau.name.trim().length < 2}
                                onClick={() => onValider(nouveau)}
                                className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                            >
                                {enCours ? 'Enregistrement…' : 'Enregistrer et livrer ici'}
                            </button>
                        </div>
                    </div>
                )}

                <div className="mt-4 flex justify-end border-t border-gray-100 pt-3">
                    <button
                        type="button"
                        onClick={onFermer}
                        className="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                    >
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    );
}
