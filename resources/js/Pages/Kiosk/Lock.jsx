import { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import QRCode from 'qrcode';

/*
 * Écran affiché à la place du mur des commandes / de la carte clando / de la
 * carte des livraisons tant que l'écran n'a pas été débloqué par un employé —
 * voir App\Support\KioskLock et
 * docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md.
 *
 * Le jeton passé en prop n'appartient qu'à ce chargement de page : s'il
 * expire avant d'être scanné (10 min), le sondage le détecte et recharge la
 * page, qui obtient alors un jeton neuf côté serveur.
 */
const INTERVALLE_SONDAGE_MS = 2000;

const TITRES = {
    commandes: 'Mur des commandes verrouillé',
    clando: 'Carte des courses verrouillée',
    commandes_carte: 'Carte des livraisons verrouillée',
};

export default function Lock({ page, token }) {
    const canvasRef = useRef(null);
    const [erreur, setErreur] = useState(null);

    useEffect(() => {
        if (canvasRef.current) {
            QRCode.toCanvas(canvasRef.current, token, { width: 280, margin: 2 });
        }
    }, [token]);

    useEffect(() => {
        const intervalle = setInterval(async () => {
            try {
                const { data } = await axios.get(`/deverrouillage/${token}/statut`);
                if (data.unlocked || data.expired) {
                    window.location.reload();
                }
                setErreur(null);
            } catch (e) {
                setErreur('Connexion au serveur interrompue, nouvelle tentative...');
            }
        }, INTERVALLE_SONDAGE_MS);

        return () => clearInterval(intervalle);
    }, [token]);

    return (
        <>
            <Head title="Écran verrouillé" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-slate-900 text-white">
                <h1 className="mb-2 text-2xl font-semibold">
                    {TITRES[page] ?? 'Écran verrouillé'}
                </h1>
                <p className="mb-8 text-slate-300">
                    Scanne ce QR code avec l'application employé pour afficher cet écran.
                </p>
                <div className="rounded-2xl bg-white p-6">
                    <canvas ref={canvasRef} />
                </div>
                {erreur && <p className="mt-6 text-sm text-amber-400">{erreur}</p>}
            </div>
        </>
    );
}
