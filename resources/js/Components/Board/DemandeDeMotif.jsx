import { useEffect, useRef, useState } from 'react';

/**
 * Demande pourquoi une commande, une livraison ou une course est annulée.
 *
 * Une annulation passait d'un clic, sans un mot. Impossible ensuite de savoir si
 * le client s'était rétracté, si le produit manquait, si l'adresse était
 * introuvable ou si aucun agent n'avait répondu — quatre problèmes distincts que
 * le même compteur d'échecs confondait, et dont un seul se corrige en recrutant.
 *
 * Les motifs courants sont proposés d'un geste : au comptoir, personne ne tape
 * une phrase pendant qu'un client attend. Le champ libre reste ouvert derrière,
 * parce qu'aucune liste ne prévoit tout.
 */
export default function DemandeDeMotif({ ouvert, titre, motifs = [], enCours = false, onAnnuler, onValider }) {
    const [motif, setMotif] = useState('');
    const champ = useRef(null);

    useEffect(() => {
        if (ouvert) {
            setMotif('');
            // Le curseur dans le champ : au clavier, on enchaîne sans la souris.
            setTimeout(() => champ.current?.focus(), 60);
        }
    }, [ouvert]);

    useEffect(() => {
        if (!ouvert) return undefined;

        const auClavier = (evenement) => {
            if (evenement.key === 'Escape') onAnnuler();
        };

        window.addEventListener('keydown', auClavier);
        return () => window.removeEventListener('keydown', auClavier);
    }, [ouvert, onAnnuler]);

    if (!ouvert) return null;

    // Trois caractères : en deçà, ce n'est pas une explication. Le serveur
    // applique la même règle — celle-ci n'est qu'un confort d'écran.
    const utilisable = motif.trim().length >= 3;

    const valider = () => {
        if (utilisable && !enCours) onValider(motif.trim());
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div className="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                <h2 className="text-lg font-bold text-gray-900">{titre}</h2>
                <p className="mt-1 text-sm text-gray-500">
                    Le motif reste attaché à la ligne : c'est ce qui permettra, plus tard, de savoir ce qui s'est passé.
                </p>

                {motifs.length > 0 && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        {motifs.map((propose) => (
                            <button
                                key={propose}
                                type="button"
                                onClick={() => setMotif(propose)}
                                className={`rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition-colors ${
                                    motif === propose
                                        ? 'bg-red-600 text-white ring-red-600'
                                        : 'bg-gray-50 text-gray-700 ring-gray-200 hover:bg-gray-100'
                                }`}
                            >
                                {propose}
                            </button>
                        ))}
                    </div>
                )}

                <textarea
                    ref={champ}
                    value={motif}
                    onChange={(evenement) => setMotif(evenement.target.value)}
                    onKeyDown={(evenement) => {
                        // Entrée valide, sauf avec Maj pour aller à la ligne.
                        if (evenement.key === 'Enter' && !evenement.shiftKey) {
                            evenement.preventDefault();
                            valider();
                        }
                    }}
                    rows={3}
                    maxLength={255}
                    placeholder="Ou écrivez le motif…"
                    className="mt-3 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0"
                />

                <div className="mt-4 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={onAnnuler}
                        className="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                    >
                        Revenir
                    </button>
                    <button
                        type="button"
                        onClick={valider}
                        disabled={!utilisable || enCours}
                        className="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    >
                        {enCours ? 'Enregistrement…' : "Confirmer l'annulation"}
                    </button>
                </div>
            </div>
        </div>
    );
}
