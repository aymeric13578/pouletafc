import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductCard from '@/Components/Shop/ProductCard';

export default function Home({ categories, products, articles }) {
    return (
        <ShopLayout>
            <Head title="Accueil" />

            <section className="relative overflow-hidden bg-gradient-to-br from-brand-700 to-brand-900 text-white">
                <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/5 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-accent-500/10 blur-3xl" />

                <div className="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
                    <div className="animate-fade-in-up">
                        <span className="inline-block rounded-full bg-white/10 px-4 py-1 text-sm font-medium text-accent-300">
                            Livraison rapide et géolocalisée
                        </span>
                        <h1 className="mt-4 text-4xl font-extrabold leading-tight sm:text-5xl">
                            Du poulet frais, commandé en ligne, livré chez vous.
                        </h1>
                        <p className="mt-4 max-w-lg text-white/80">
                            Poulet AFC vous propose une sélection de produits frais et de qualité,
                            avec un paiement simple et une livraison suivie en temps réel.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={route('shop.catalog.index')}
                                className="rounded-full bg-white px-6 py-3 text-sm font-semibold text-brand-700 shadow-lg shadow-brand-950/20 transition-all duration-300 hover:shadow-xl active:scale-95"
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
                    <div
                        style={{ animationDelay: '150ms' }}
                        className="hidden animate-fade-in-up justify-center lg:flex"
                    >
                        <div className="relative flex h-72 w-72 items-center justify-center rounded-3xl bg-white p-8 shadow-2xl">
                            <div className="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-accent-400/20 blur-2xl" />
                            <div className="pointer-events-none absolute -bottom-8 -left-8 h-24 w-24 rounded-full bg-brand-300/30 blur-2xl" />
                            <img src="/images/logo.png" alt="Poulet AFC" className="relative h-full w-full rounded-2xl object-cover" />
                        </div>
                    </div>
                </div>
            </section>

            {categories.length > 0 && (
                <section
                    style={{ animationDelay: '100ms' }}
                    className="mx-auto max-w-7xl animate-fade-in-up px-4 py-14 sm:px-6 lg:px-8"
                >
                    <h2 className="text-xl font-bold text-gray-900">
                        Catégories <span className="text-brand-600">populaires</span>
                    </h2>
                    <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        {categories.map((category, index) => (
                            <Link
                                key={category.id}
                                href={route('shop.catalog.index', { category: category.id })}
                                style={{ animationDelay: `${Math.min(index, 8) * 60}ms` }}
                                className="group flex animate-fade-in-up flex-col items-center gap-3 rounded-2xl bg-white p-4 text-center shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            >
                                <img src={category.image_url} alt={category.name} className="h-16 w-16 rounded-full object-cover shadow-sm transition-transform duration-300 group-hover:scale-110" />
                                <span className="text-sm font-medium text-gray-700 group-hover:text-brand-700">{category.name}</span>
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
                    <div className="flex items-center justify-between">
                        <h2 className="text-xl font-bold text-gray-900">
                            Nos produits <span className="text-brand-600">populaires</span>
                        </h2>
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

            {articles.length > 0 && (
                <section
                    style={{ animationDelay: '250ms' }}
                    className="mx-auto max-w-7xl animate-fade-in-up px-4 pb-16 sm:px-6 lg:px-8"
                >
                    <div className="flex items-center justify-between">
                        <h2 className="text-xl font-bold text-gray-900">
                            Actualités <span className="text-brand-600">&amp; promotions</span>
                        </h2>
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
