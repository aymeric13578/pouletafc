import { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductCard from '@/Components/Shop/ProductCard';

const HERO_SLIDES = [
    '/images/products/poulet-entier-1.jpg',
    '/images/categories/poulet-rotis.jpg',
    '/images/products/cuisses-poulet.jpg',
    '/images/products/poulet-bio.jpg',
];

export default function Home({ categories, products, articles }) {
    const [slide, setSlide] = useState(0);

    useEffect(() => {
        const timer = setInterval(() => {
            setSlide((s) => (s + 1) % HERO_SLIDES.length);
        }, 5000);
        return () => clearInterval(timer);
    }, []);

    const prevSlide = () => setSlide((s) => (s - 1 + HERO_SLIDES.length) % HERO_SLIDES.length);
    const nextSlide = () => setSlide((s) => (s + 1) % HERO_SLIDES.length);

    return (
        <ShopLayout>
            <Head title="Accueil" />

            <section className="bg-gray-50 pt-6 sm:pt-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="relative animate-fade-in-up overflow-hidden rounded-3xl shadow-2xl">
                        {HERO_SLIDES.map((src, index) => (
                            <img
                                key={src}
                                src={src}
                                alt=""
                                className={`absolute inset-0 h-full w-full object-cover transition-opacity duration-1000 ease-in-out ${
                                    index === slide ? 'opacity-100' : 'opacity-0'
                                }`}
                            />
                        ))}
                        <div className="absolute inset-0 bg-gradient-to-r from-brand-900/95 via-brand-900/70 to-brand-900/20" />

                        <button
                            type="button"
                            onClick={prevSlide}
                            aria-label="Image précédente"
                            className="absolute left-4 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/25 active:scale-90 sm:flex"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            onClick={nextSlide}
                            aria-label="Image suivante"
                            className="absolute right-4 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/25 active:scale-90 sm:flex"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <div className="absolute bottom-5 right-6 z-10 flex gap-2">
                            {HERO_SLIDES.map((_, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    onClick={() => setSlide(index)}
                                    aria-label={`Aller à l'image ${index + 1}`}
                                    className={`h-2 rounded-full transition-all duration-300 ${
                                        index === slide ? 'w-6 bg-white' : 'w-2 bg-white/40 hover:bg-white/60'
                                    }`}
                                />
                            ))}
                        </div>

                        <div className="relative flex min-h-[420px] flex-col justify-center px-6 py-16 sm:px-12 sm:py-20 lg:min-h-[480px] lg:px-16">
                            <span className="inline-block w-fit rounded-full bg-white/10 px-4 py-1 text-sm font-medium text-accent-300 backdrop-blur-sm">
                                Livraison rapide et géolocalisée
                            </span>
                            <h1 className="mt-4 max-w-xl text-4xl font-extrabold leading-tight text-white sm:text-5xl">
                                Du poulet frais, commandé en ligne, livré chez vous.
                            </h1>
                            <p className="mt-4 max-w-lg text-white/80">
                                Poulet AFC vous propose une sélection de produits frais et de qualité,
                                avec un paiement simple et une livraison suivie en temps réel.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    href={route('shop.catalog.index')}
                                    className="rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-accent-900/30 transition-all duration-300 hover:bg-accent-600 hover:shadow-xl active:scale-95"
                                >
                                    Découvrir la boutique
                                </Link>
                                <Link
                                    href={route('shop.articles.index')}
                                    className="rounded-full border border-white/30 px-6 py-3 text-sm font-semibold text-white transition-all duration-300 hover:bg-white/10 active:scale-95"
                                >
                                    Nos actualités
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {categories.length > 0 && (
                <section
                    style={{ animationDelay: '100ms' }}
                    className="mx-auto max-w-7xl animate-fade-in-up px-4 py-14 sm:px-6 lg:px-8"
                >
                    <div className="flex items-end justify-between">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-accent-600">Nos rayons</span>
                            <h2 className="mt-1 text-xl font-bold text-gray-900">
                                Parcourez par <span className="text-brand-600">catégorie</span>
                            </h2>
                        </div>
                        <Link href={route('shop.catalog.index')} className="hidden shrink-0 text-sm font-semibold text-brand-700 transition-colors duration-300 hover:text-brand-800 hover:underline sm:block">
                            Voir tout →
                        </Link>
                    </div>
                    <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        {categories.map((category, index) => (
                            <Link
                                key={category.id}
                                href={route('shop.catalog.index', { category: category.id })}
                                style={{ animationDelay: `${Math.min(index, 8) * 60}ms` }}
                                className="group relative aspect-square animate-fade-in-up overflow-hidden rounded-2xl shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            >
                                <img
                                    src={category.image_url}
                                    alt={category.name}
                                    className="absolute inset-0 h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-110"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent" />
                                <span className="absolute inset-x-0 bottom-0 p-3 text-sm font-semibold text-white">{category.name}</span>
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            {products.length > 0 && (
                <section
                    style={{ animationDelay: '200ms' }}
                    className="mx-auto max-w-7xl animate-fade-in-up px-4 pb-14 sm:px-6 lg:px-8"
                >
                    <div className="flex items-end justify-between">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-accent-600">Sélection du moment</span>
                            <h2 className="mt-1 text-xl font-bold text-gray-900">
                                Nos produits <span className="text-brand-600">populaires</span>
                            </h2>
                        </div>
                        <Link href={route('shop.catalog.index')} className="text-sm font-semibold text-brand-700 transition-colors duration-300 hover:text-brand-800 hover:underline">
                            Voir tout →
                        </Link>
                    </div>
                    <div className="mt-6 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                        {products.map((product, index) => (
                            <ProductCard key={product.id} product={product} index={index} />
                        ))}
                    </div>
                </section>
            )}

            <section className="bg-gray-50 px-4 pb-14 sm:px-6 lg:px-8">
                <div className="relative mx-auto max-w-7xl animate-fade-in-up overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 to-brand-900 shadow-2xl">
                    <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/5 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-accent-500/10 blur-3xl" />

                    <div className="relative grid gap-10 px-6 py-14 sm:px-10 sm:py-16 lg:grid-cols-2 lg:items-center lg:px-16">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-accent-300">Bientôt disponible</span>
                            <h2 className="mt-2 text-3xl font-extrabold text-white sm:text-4xl">Notre application mobile</h2>
                            <p className="mt-4 max-w-md text-white/70">
                                Commandez votre poulet frais en quelques clics et suivez votre livraison en temps réel,
                                directement depuis votre téléphone.
                            </p>

                            <ul className="mt-8 space-y-5">
                                {[
                                    { title: 'Interface intuitive', desc: 'Design moderne et facile à utiliser pour tous' },
                                    { title: 'Géolocalisation en temps réel', desc: "Suivez votre livreur sur la carte jusqu'à votre porte" },
                                    { title: 'Paiement sécurisé', desc: 'Plusieurs options de paiement sécurisées' },
                                    { title: 'Livraison rapide', desc: 'Commandez maintenant, recevez en quelques heures' },
                                ].map((item) => (
                                    <li key={item.title} className="flex items-start gap-4">
                                        <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-lg">
                                            🍗
                                        </span>
                                        <div>
                                            <p className="font-semibold text-white">{item.title}</p>
                                            <p className="text-sm text-white/60">{item.desc}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-9 flex flex-wrap gap-3">
                                <a
                                    href="#"
                                    title="Bientôt disponible sur l'App Store"
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
                                <a
                                    href="#"
                                    title="Bientôt disponible sur Google Play"
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
                            </div>
                        </div>

                        <div className="hidden justify-center lg:flex">
                            <div className="relative h-[420px] w-[220px] rounded-[2.5rem] border-4 border-white/20 bg-white/5 p-3 shadow-2xl backdrop-blur-sm">
                                <div className="absolute left-1/2 top-2 h-4 w-20 -translate-x-1/2 rounded-full bg-brand-950/60" />
                                <div className="h-full overflow-hidden rounded-[2rem] bg-white">
                                    <div className="flex h-1/3 items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800 text-5xl">
                                        🍗
                                    </div>
                                    <div className="space-y-3 p-4">
                                        <div className="h-16 animate-pulse rounded-xl bg-gray-100" />
                                        <div className="h-16 animate-pulse rounded-xl bg-gray-100" style={{ animationDelay: '150ms' }} />
                                        <div className="h-16 animate-pulse rounded-xl bg-gray-100" style={{ animationDelay: '300ms' }} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {articles.length > 0 && (
                <section
                    style={{ animationDelay: '250ms' }}
                    className="mx-auto max-w-7xl animate-fade-in-up px-4 pb-16 sm:px-6 lg:px-8"
                >
                    <div className="flex items-end justify-between">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-accent-600">À la une</span>
                            <h2 className="mt-1 text-xl font-bold text-gray-900">
                                Notre <span className="text-brand-600">blog</span>
                            </h2>
                        </div>
                        <Link href={route('shop.articles.index')} className="text-sm font-semibold text-brand-700 transition-colors duration-300 hover:text-brand-800 hover:underline">
                            Tout voir →
                        </Link>
                    </div>
                    <div className="mt-6 grid gap-5 sm:grid-cols-3">
                        {articles.map((article, index) => (
                            <Link
                                key={article.id}
                                href={route('shop.articles.show', article.id)}
                                style={{ animationDelay: `${Math.min(index, 8) * 60}ms` }}
                                className="group animate-fade-in-up overflow-hidden rounded-2xl bg-white shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            >
                                <div className="aspect-video w-full overflow-hidden bg-gray-100">
                                    <img src={article.image_url} alt={article.title} className="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-110" />
                                </div>
                                <div className="p-5">
                                    <h3 className="line-clamp-2 font-semibold text-gray-900">{article.title}</h3>
                                    <p className="mt-2 line-clamp-2 text-sm text-gray-500">{article.excerpt}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </ShopLayout>
    );
}
