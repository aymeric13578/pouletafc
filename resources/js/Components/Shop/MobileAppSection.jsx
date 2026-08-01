import { Link } from '@inertiajs/react';

const FEATURES = [
    {
        title: 'Commande en 3 clics',
        desc: 'Votre panier habituel se recommande en une seule touche.',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
        ),
    },
    {
        title: 'Suivi du livreur en direct',
        desc: 'Sa position sur la carte, en temps réel, jusqu\'à votre porte.',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
        ),
    },
    {
        title: 'Paiement sécurisé',
        desc: 'Mobile Money, carte ou espèces à la livraison.',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        ),
    },
    {
        title: 'Notifications de livraison',
        desc: 'Prévenu au départ de la commande et à l\'arrivée du livreur.',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        ),
    },
];

function FeatureIcon({ children }) {
    return (
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-inset ring-white/15">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className="h-5 w-5 text-accent-300">
                {children}
            </svg>
        </span>
    );
}

export default function MobileAppSection({ app }) {
    const android = app?.android ?? {};
    const ios = app?.ios ?? {};
    const name = app?.name ?? 'CLANDO';

    const apkAvailable = Boolean(android.apk_available);
    const onPlayStore = Boolean(android.play_store_url);

    // Détails affichés sous le bouton principal (version / poids / date), sans
    // trous si l'un d'eux n'est pas renseigné en configuration.
    const apkMeta = [
        android.version && `Version ${android.version}`,
        android.apk_size,
        android.min_os && `${android.min_os} minimum`,
    ].filter(Boolean);

    return (
        <section className="bg-gray-50 px-4 pb-14 sm:px-6 lg:px-8">
            <div className="relative mx-auto max-w-7xl animate-fade-in-up overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 to-brand-900 shadow-2xl">
                <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/5 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-accent-500/10 blur-3xl" />

                <div className="relative grid gap-x-12 gap-y-10 px-6 py-14 sm:px-10 sm:py-16 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center lg:px-16">
                    <div>
                        <span className="inline-flex items-center gap-2 rounded-full bg-accent-500/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-accent-300 ring-1 ring-inset ring-accent-400/30">
                            <span className="relative flex h-1.5 w-1.5">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75" />
                                <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent-400" />
                            </span>
                            {apkAvailable ? 'Disponible maintenant' : 'Bientôt disponible'}
                        </span>

                        <h2 className="mt-4 text-3xl font-extrabold text-white sm:text-4xl">
                            L'application <span className="text-accent-300">{name}</span>
                        </h2>
                        <p className="mt-4 max-w-md text-white/70">
                            Commandez votre poulet frais et suivez votre livraison en temps réel depuis votre
                            téléphone. {apkAvailable
                                ? 'Installez-la dès aujourd\'hui, sans attendre le Play Store.'
                                : 'Elle arrive très bientôt sur vos téléphones.'}
                        </p>

                        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                            {apkAvailable ? (
                                <a
                                    href={android.apk_url}
                                    className="group inline-flex items-center justify-center gap-3 rounded-full bg-accent-500 px-6 py-3.5 font-bold text-white shadow-lg shadow-accent-900/30 transition-all duration-300 hover:bg-accent-400 hover:shadow-xl active:scale-95"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5 transition-transform duration-300 group-hover:translate-y-0.5">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    Télécharger l'APK Android
                                </a>
                            ) : (
                                <span className="inline-flex items-center justify-center gap-3 rounded-full bg-white/10 px-6 py-3.5 font-bold text-white/50 ring-1 ring-inset ring-white/15">
                                    Téléchargement bientôt ouvert
                                </span>
                            )}

                            <Link
                                href={route('shop.app.show')}
                                className="inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-semibold text-white/80 transition-colors duration-300 hover:text-white hover:underline"
                            >
                                Comment l'installer ?
                            </Link>
                        </div>

                        {apkMeta.length > 0 && (
                            <p className="mt-3 text-xs text-white/50">
                                {apkMeta.join(' · ')}
                                {android.apk_updated_at && ` · mis en ligne le ${android.apk_updated_at}`}
                            </p>
                        )}

                        <div className="mt-8 flex flex-wrap items-center gap-3 border-t border-white/10 pt-6">
                            {onPlayStore ? (
                                <a
                                    href={android.play_store_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-3 rounded-full bg-white px-5 py-3 text-brand-800 shadow-lg transition-all duration-300 hover:shadow-xl active:scale-95"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-6 w-6">
                                        <path d="M3.6 2.35c-.35.36-.55.9-.55 1.6v16.1c0 .7.2 1.24.56 1.6l.1.08L13 12.5v-.24L3.7 2.27l-.1.08z" />
                                        <path d="M16.15 15.66l-3.15-3.16v-.24l3.16-3.16.07.04 3.74 2.13c1.07.6 1.07 1.6 0 2.2l-3.75 2.15-.07.04z" />
                                        <path d="M16.22 15.7 13 12.5l-9.4 9.4c.35.37.93.42 1.58.05l10.94-6.13-.9-.12z" />
                                        <path d="M16.22 9.3 5.28 3.17c-.65-.37-1.23-.32-1.58.05L13 12.5l3.22-3.2z" />
                                    </svg>
                                    <span className="text-left leading-tight">
                                        <span className="block text-[10px] opacity-70">Disponible sur</span>
                                        <span className="block text-sm font-bold">Google Play</span>
                                    </span>
                                </a>
                            ) : (
                                <span className="flex items-center gap-3 rounded-full bg-white/5 px-5 py-3 text-white/50 ring-1 ring-inset ring-white/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-6 w-6">
                                        <path d="M3.6 2.35c-.35.36-.55.9-.55 1.6v16.1c0 .7.2 1.24.56 1.6l.1.08L13 12.5v-.24L3.7 2.27l-.1.08z" />
                                        <path d="M16.15 15.66l-3.15-3.16v-.24l3.16-3.16.07.04 3.74 2.13c1.07.6 1.07 1.6 0 2.2l-3.75 2.15-.07.04z" />
                                        <path d="M16.22 15.7 13 12.5l-9.4 9.4c.35.37.93.42 1.58.05l10.94-6.13-.9-.12z" />
                                        <path d="M16.22 9.3 5.28 3.17c-.65-.37-1.23-.32-1.58.05L13 12.5l3.22-3.2z" />
                                    </svg>
                                    <span className="text-left leading-tight">
                                        <span className="block text-[10px] opacity-80">Google Play</span>
                                        <span className="block text-sm font-bold">En cours de validation</span>
                                    </span>
                                </span>
                            )}

                            {ios.app_store_url ? (
                                <a
                                    href={ios.app_store_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-3 rounded-full bg-white px-5 py-3 text-brand-800 shadow-lg transition-all duration-300 hover:shadow-xl active:scale-95"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-6 w-6">
                                        <path d="M16.365 1.43c0 1.14-.44 2.06-1.32 2.86-.89.79-1.85 1.28-2.98 1.19-.09-1.13.44-2.22 1.31-2.98.86-.75 2.14-1.24 2.99-1.07zM20.5 17.19c-.5 1.14-.74 1.66-1.38 2.68-.9 1.43-2.16 3.21-3.73 3.22-1.4.01-1.76-.91-3.66-.9-1.9.01-2.29.92-3.69.9-1.57-.01-2.76-1.62-3.66-3.05-2.5-3.94-2.76-8.57-1.22-11.03.99-1.58 2.55-2.5 4.02-2.5 1.5 0 2.44.9 3.68.9 1.2 0 1.94-.9 3.68-.9 1.24 0 2.56.68 3.5 1.85-3.08 1.69-2.58 6.09.46 7.83z" />
                                    </svg>
                                    <span className="text-left leading-tight">
                                        <span className="block text-[10px] opacity-70">Télécharger sur</span>
                                        <span className="block text-sm font-bold">App Store</span>
                                    </span>
                                </a>
                            ) : (
                                <span className="flex items-center gap-3 rounded-full bg-white/5 px-5 py-3 text-white/50 ring-1 ring-inset ring-white/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-6 w-6">
                                        <path d="M16.365 1.43c0 1.14-.44 2.06-1.32 2.86-.89.79-1.85 1.28-2.98 1.19-.09-1.13.44-2.22 1.31-2.98.86-.75 2.14-1.24 2.99-1.07zM20.5 17.19c-.5 1.14-.74 1.66-1.38 2.68-.9 1.43-2.16 3.21-3.73 3.22-1.4.01-1.76-.91-3.66-.9-1.9.01-2.29.92-3.69.9-1.57-.01-2.76-1.62-3.66-3.05-2.5-3.94-2.76-8.57-1.22-11.03.99-1.58 2.55-2.5 4.02-2.5 1.5 0 2.44.9 3.68.9 1.2 0 1.94-.9 3.68-.9 1.24 0 2.56.68 3.5 1.85-3.08 1.69-2.58 6.09.46 7.83z" />
                                    </svg>
                                    <span className="text-left leading-tight">
                                        <span className="block text-[10px] opacity-80">App Store</span>
                                        <span className="block text-sm font-bold">Bientôt</span>
                                    </span>
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {FEATURES.map((feature) => (
                            <div
                                key={feature.title}
                                className="rounded-2xl bg-white/5 p-5 ring-1 ring-inset ring-white/10 transition-colors duration-300 hover:bg-white/10"
                            >
                                <FeatureIcon>{feature.icon}</FeatureIcon>
                                <p className="mt-4 font-semibold text-white">{feature.title}</p>
                                <p className="mt-1 text-sm leading-relaxed text-white/60">{feature.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
