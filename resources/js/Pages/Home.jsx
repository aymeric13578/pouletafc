import { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductCard from '@/Components/Shop/ProductCard';
import MobileAppSection from '@/Components/Shop/MobileAppSection';

const HERO_SLIDES = [
    '/images/products/poulet-entier-1.jpg',
    '/images/categories/poulet-rotis.jpg',
    '/images/products/cuisses-poulet.jpg',
    '/images/products/poulet-bio.jpg',
];

export default function Home({ categories, products, articles, mobileApp }) {
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

            <MobileAppSection app={mobileApp} />

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
