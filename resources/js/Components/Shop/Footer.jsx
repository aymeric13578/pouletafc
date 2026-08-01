import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';

export default function Footer() {
    const { data, setData, post, processing, reset, recentlySuccessful } = useForm({ email: '' });
    const [year] = useState(new Date().getFullYear());

    const subscribe = (e) => {
        e.preventDefault();
        post(route('shop.newsletter.subscribe'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <footer className="mt-16 bg-brand-900">
            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div className="grid gap-10 md:grid-cols-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <img src="/images/logo.png" alt="Poulet AFC" className="h-9 w-9 rounded-full object-cover shadow-sm" />
                            <span className="text-lg font-extrabold text-white">Poulet AFC</span>
                        </div>
                        <p className="mt-3 text-sm leading-relaxed text-white/60">
                            Élevage et livraison de poulet et produits frais, commandés en ligne et livrés chez vous.
                        </p>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-white">Boutique</h3>
                        <ul className="mt-4 space-y-2.5 text-sm text-white/60">
                            <li><Link href={route('shop.catalog.index')} className="transition-colors duration-300 hover:text-white">Tous les produits</Link></li>
                            <li><Link href={route('shop.articles.index')} className="transition-colors duration-300 hover:text-white">Blog</Link></li>
                            <li><Link href={route('shop.cart.index')} className="transition-colors duration-300 hover:text-white">Mon panier</Link></li>
                            <li><Link href={route('shop.app.show')} className="transition-colors duration-300 hover:text-white">Application mobile</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-white">Informations</h3>
                        <ul className="mt-4 space-y-2.5 text-sm text-white/60">
                            <li><Link href={route('shop.pages.cgv')} className="transition-colors duration-300 hover:text-white">Conditions générales de vente</Link></li>
                            <li><Link href="/privacy" className="transition-colors duration-300 hover:text-white">Mentions légales</Link></li>
                            <li><Link href={route('shop.pages.faq')} className="transition-colors duration-300 hover:text-white">FAQ</Link></li>
                            <li><Link href={route('shop.pages.contact')} className="transition-colors duration-300 hover:text-white">Contact</Link></li>
                            <li><Link href={route('shop.pages.delete-account')} className="transition-colors duration-300 hover:text-white">Supprimer mon compte</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-white">Newsletter</h3>
                        <p className="mt-4 text-sm text-white/60">Recevez nos offres et promotions par e-mail.</p>
                        <form onSubmit={subscribe} className="mt-3 flex gap-2">
                            <input
                                type="email"
                                required
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="votre@email.com"
                                className="w-full rounded-full border-white/10 bg-white/10 px-4 py-2 text-sm text-white placeholder:text-white/40 transition-all duration-300 focus:border-white/30 focus:bg-white/20 focus:ring-2 focus:ring-white/30"
                            />
                            <button
                                type="submit"
                                disabled={processing}
                                className="shrink-0 rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:bg-brand-500 hover:shadow-md active:scale-95 disabled:opacity-60"
                            >
                                OK
                            </button>
                        </form>
                        {recentlySuccessful && <p className="mt-2 animate-fade-in-up text-xs font-medium text-accent-300">Merci pour votre inscription !</p>}
                    </div>
                </div>

                <div className="mt-10 border-t border-white/10 pt-6 text-center text-xs text-white/40">
                    © {year} Poulet AFC. Tous droits réservés.
                </div>
            </div>
        </footer>
    );
}
