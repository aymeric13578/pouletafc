import { Head, useForm, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function DeleteAccount() {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        identifier: '',
        password: '',
        confirm: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('shop.pages.delete-account.process'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <ShopLayout>
            <Head title="Supprimer mon compte Poulet AFC" />

            <section className="relative overflow-hidden bg-gradient-to-br from-brand-700 to-brand-900 py-16 text-center text-white sm:py-20">
                <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/5 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-accent-500/10 blur-3xl" />
                <div className="relative mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <h1 className="animate-fade-in-up text-3xl font-extrabold sm:text-4xl">Suppression de compte</h1>
                    <p className="mt-3 animate-fade-in-up text-white/70">
                        Application <span className="font-semibold text-white">Poulet AFC</span> — supprimez votre compte
                        et vos données personnelles en toute simplicité.
                    </p>
                </div>
            </section>

            <div className="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
                {/* Explications de conformité */}
                <div className="prose mb-8 max-w-none animate-fade-in-up text-gray-600">
                    <p>
                        Cette page vous permet de demander la suppression définitive de votre compte
                        <strong> Poulet AFC</strong> (édité par Poulet AFC) ainsi que des données personnelles
                        associées à ce compte.
                    </p>
                    <h2>Données qui seront supprimées</h2>
                    <ul>
                        <li>Vos informations de profil (nom, e-mail, téléphone, WhatsApp, ville, photo).</li>
                        <li>Vos paniers et articles enregistrés.</li>
                        <li>Vos adresses de livraison.</li>
                        <li>L'historique d'activité lié à votre compte.</li>
                    </ul>
                    <p>
                        La suppression est <strong>immédiate et irréversible</strong>. Pour des raisons légales et
                        comptables, certaines données de facturation liées à des commandes déjà livrées peuvent être
                        conservées de façon anonymisée pendant la durée imposée par la loi.
                    </p>
                    <p>
                        Vous pouvez aussi effectuer cette opération directement depuis l'application mobile Poulet AFC,
                        rubrique <em>Mon compte</em>. Pour toute question&nbsp;: <strong>infos@pouletafc.com</strong>.
                    </p>
                </div>

                {flash?.success ? (
                    <div className="animate-fade-in-up rounded-2xl border border-green-200 bg-green-50 p-6 text-center shadow-sm">
                        <p className="text-sm font-semibold text-green-700">{flash.success}</p>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        style={{ animationDelay: '80ms' }}
                        className="animate-fade-in-up space-y-4 rounded-2xl bg-white p-6 shadow-2xl sm:p-8"
                    >
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                E-mail ou numéro de téléphone
                            </label>
                            <input
                                type="text"
                                value={data.identifier}
                                onChange={(e) => setData('identifier', e.target.value)}
                                placeholder="votre@email.com ou +237..."
                                className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                            />
                            {errors.identifier && <p className="mt-1 text-xs text-red-600">{errors.identifier}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Mot de passe</label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Votre mot de passe"
                                className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                            />
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                        </div>

                        <label className="flex items-start gap-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                            <input
                                type="checkbox"
                                checked={data.confirm}
                                onChange={(e) => setData('confirm', e.target.checked)}
                                className="mt-0.5 rounded border-red-300 text-red-600 focus:ring-red-500"
                            />
                            <span>
                                Je comprends que cette action est <strong>définitive</strong> et que mon compte ainsi que
                                mes données personnelles seront supprimés.
                            </span>
                        </label>
                        {errors.confirm && <p className="text-xs text-red-600">{errors.confirm}</p>}

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-full bg-red-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-red-700 hover:shadow-md active:scale-95 disabled:opacity-60 disabled:active:scale-100"
                        >
                            Supprimer définitivement mon compte
                        </button>
                    </form>
                )}
            </div>
        </ShopLayout>
    );
}
