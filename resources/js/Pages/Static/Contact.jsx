import { Head, useForm } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Contact() {
    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        name: '',
        email: '',
        message: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('shop.pages.contact.send'), { onSuccess: () => reset() });
    };

    return (
        <ShopLayout>
            <Head title="Contact" />

            <section className="relative overflow-hidden bg-gradient-to-br from-brand-700 to-brand-900 py-16 text-center text-white sm:py-20">
                <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/5 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-accent-500/10 blur-3xl" />
                <div className="relative mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <h1 className="animate-fade-in-up text-3xl font-extrabold sm:text-4xl">Contactez-nous</h1>
                    <p className="mt-3 animate-fade-in-up text-white/70">
                        Une question sur votre commande, un produit, ou une suggestion ? Écrivez-nous.
                    </p>
                </div>
            </section>

            <div className="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
                <form
                    onSubmit={submit}
                    style={{ animationDelay: '80ms' }}
                    className="-mt-24 animate-fade-in-up space-y-4 rounded-2xl bg-white p-6 shadow-2xl sm:p-8"
                >
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Nom</label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Votre nom"
                            className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                        />
                        {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">E-mail</label>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="votre@email.com"
                            className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                        />
                        {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Message</label>
                        <textarea
                            rows={5}
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            placeholder="Votre message..."
                            className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                        />
                        {errors.message && <p className="mt-1 text-xs text-red-600">{errors.message}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-full bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95 disabled:opacity-60 disabled:active:scale-100"
                    >
                        Envoyer le message
                    </button>

                    {recentlySuccessful && <p className="animate-fade-in-up text-sm font-medium text-green-600">Message envoyé avec succès !</p>}
                </form>
            </div>
        </ShopLayout>
    );
}
