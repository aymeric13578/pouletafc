import { useState } from 'react';
import { formatPrice } from '@/Utils/format';

/**
 * Boutons de partage d'un produit.
 *
 * L'aperçu riche (image + description) affiché par WhatsApp ne dépend pas de ce
 * composant : il provient des balises Open Graph rendues côté serveur dans
 * resources/views/partials/social-meta.blade.php. Ici on ne fait que composer le
 * message et ouvrir l'application.
 */
export default function ShareProduct({ product }) {
    const [copied, setCopied] = useState(false);

    const url = product.share_url;
    // WhatsApp ne génère l'aperçu que du premier lien du message : on place
    // l'URL en dernier, seule sur sa ligne, pour qu'elle soit bien détectée.
    const message = `${product.name} — ${formatPrice(product.price)}\n${url}`;

    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;

    // navigator.share n'existe que sur mobile et en contexte sécurisé (https).
    const canNativeShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function';

    const nativeShare = () => {
        navigator
            .share({ title: product.name, text: product.share_description, url })
            .catch(() => {
                // L'utilisateur a fermé la feuille de partage : rien à signaler.
            });
    };

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            // Safari < 13.1 et contextes non sécurisés n'exposent pas le presse-papiers.
            const field = document.createElement('textarea');
            field.value = url;
            document.body.appendChild(field);
            field.select();
            document.execCommand('copy');
            document.body.removeChild(field);
        }

        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="mt-6 border-t border-gray-100 pt-5">
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">Partager ce produit</p>

            <div className="mt-3 flex flex-wrap items-center gap-2">
                <a
                    href={whatsappUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-[#1eb855] hover:shadow-md active:scale-95"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-4 w-4">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.825 9.825 0 016.988 2.898 9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                    </svg>
                    WhatsApp
                </a>

                {canNativeShare && (
                    <button
                        type="button"
                        onClick={nativeShare}
                        className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-300 hover:border-brand-400 hover:text-brand-700 active:scale-95"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                        </svg>
                        Partager
                    </button>
                )}

                <button
                    type="button"
                    onClick={copyLink}
                    className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-300 hover:border-brand-400 hover:text-brand-700 active:scale-95"
                >
                    {copied ? (
                        <>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-4 w-4 text-green-600">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Lien copié
                        </>
                    ) : (
                        <>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                            </svg>
                            Copier le lien
                        </>
                    )}
                </button>
            </div>
        </div>
    );
}
