import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

const INSTALL_STEPS = [
    {
        title: 'Téléchargez le fichier',
        desc: "Appuyez sur le bouton ci-dessus depuis votre téléphone Android. Le fichier .apk se télécharge en quelques secondes.",
    },
    {
        title: 'Autorisez l\'installation',
        desc: "Android vous demandera d'autoriser l'installation depuis cette source. C'est normal : l'application ne vient pas encore du Play Store. Appuyez sur « Paramètres » puis activez « Autoriser depuis cette source ».",
    },
    {
        title: 'Ouvrez le fichier téléchargé',
        desc: "Depuis la barre de notifications ou le dossier « Téléchargements », appuyez sur le fichier, puis sur « Installer ».",
    },
    {
        title: 'Créez votre compte',
        desc: "Lancez l'application, inscrivez-vous avec votre numéro de téléphone, et vous pouvez commander.",
    },
];

export default function MobileApp({ app }) {
    const android = app?.android ?? {};
    const ios = app?.ios ?? {};
    const name = app?.name ?? 'CLANDO';

    const apkAvailable = Boolean(android.apk_available);

    const apkMeta = [
        android.version && `Version ${android.version}`,
        android.apk_size,
        android.min_os && `${android.min_os} minimum`,
        android.apk_updated_at && `Mis en ligne le ${android.apk_updated_at}`,
    ].filter(Boolean);

    return (
        <ShopLayout>
            <Head title={`Télécharger l'application ${name}`} />

            <section className="relative overflow-hidden bg-gradient-to-br from-brand-700 to-brand-900 py-16 text-center text-white sm:py-20">
                <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/5 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-accent-500/10 blur-3xl" />
                <div className="relative mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <h1 className="animate-fade-in-up text-3xl font-extrabold sm:text-4xl">
                        Application <span className="text-accent-300">{name}</span>
                    </h1>
                    <p className="mt-3 animate-fade-in-up text-white/70">
                        Commandez votre poulet frais et suivez votre livraison en temps réel. Notre fiche Google Play
                        est en cours de validation : en attendant, installez l'application directement depuis cette page.
                    </p>

                    <div className="mt-8 animate-fade-in-up">
                        {apkAvailable ? (
                            <a
                                href={android.apk_url}
                                className="group inline-flex items-center justify-center gap-3 rounded-full bg-accent-500 px-7 py-4 text-lg font-bold text-white shadow-lg shadow-accent-900/30 transition-all duration-300 hover:bg-accent-400 hover:shadow-xl active:scale-95"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5 transition-transform duration-300 group-hover:translate-y-0.5">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Télécharger pour Android
                            </a>
                        ) : (
                            <span className="inline-flex items-center justify-center gap-3 rounded-full bg-white/10 px-7 py-4 text-lg font-bold text-white/50 ring-1 ring-inset ring-white/15">
                                Téléchargement bientôt ouvert
                            </span>
                        )}

                        {apkMeta.length > 0 && (
                            <p className="mt-3 text-xs text-white/50">{apkMeta.join(' · ')}</p>
                        )}
                    </div>

                    {android.play_store_url && (
                        <p className="mt-6 text-sm text-white/70">
                            L'application est aussi disponible sur{' '}
                            <a href={android.play_store_url} target="_blank" rel="noopener noreferrer" className="font-semibold text-accent-300 underline">
                                Google Play
                            </a>
                            .
                        </p>
                    )}
                </div>
            </section>

            <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 className="animate-fade-in-up text-xl font-bold text-gray-900">Installation en 4 étapes</h2>
                <p className="mt-1 animate-fade-in-up text-sm text-gray-500">
                    Sur Android uniquement. Comptez moins de deux minutes.
                </p>

                <ol className="mt-8 space-y-6">
                    {INSTALL_STEPS.map((step, index) => (
                        <li key={step.title} className="flex animate-fade-in-up items-start gap-4">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 font-bold text-white">
                                {index + 1}
                            </span>
                            <div>
                                <p className="font-semibold text-gray-900">{step.title}</p>
                                <p className="mt-1 text-sm leading-relaxed text-gray-600">{step.desc}</p>
                            </div>
                        </li>
                    ))}
                </ol>

                <div className="mt-10 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p className="font-semibold text-amber-900">L'avertissement d'Android est-il inquiétant ?</p>
                    <p className="mt-1 text-sm leading-relaxed text-amber-800">
                        Non. Android affiche systématiquement ce message pour toute application installée en dehors du
                        Play Store. Le fichier proposé ici est celui publié par Poulet AFC, hébergé sur notre propre
                        serveur. Ne téléchargez l'application {name} depuis aucun autre site.
                    </p>
                </div>

                {!ios.app_store_url && (
                    <div className="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <p className="font-semibold text-gray-900">Vous êtes sur iPhone ?</p>
                        <p className="mt-1 text-sm leading-relaxed text-gray-600">
                            La version iOS n'est pas encore disponible. En attendant, vous pouvez commander directement
                            depuis ce site : il fonctionne parfaitement sur mobile.
                        </p>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
