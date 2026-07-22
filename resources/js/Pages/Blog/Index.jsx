import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import Pagination from '@/Components/Shop/Pagination';
import EmptyState from '@/Components/Shop/EmptyState';

export default function Index({ articles }) {
    return (
        <ShopLayout>
            <Head title="Actualités" />

            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="mb-8 animate-fade-in-up text-2xl font-extrabold text-gray-900">
                    Actualités <span className="text-brand-600">&amp; promotions</span>
                </h1>

                {articles.data.length === 0 ? (
                    <EmptyState title="Aucun article publié pour le moment" />
                ) : (
                    <>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {articles.data.map((article, index) => (
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
                                        <h2 className="line-clamp-2 font-semibold text-gray-900">{article.title}</h2>
                                        <p className="mt-2 line-clamp-3 text-sm text-gray-500">{article.excerpt}</p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                        <Pagination meta={articles} />
                    </>
                )}
            </div>
        </ShopLayout>
    );
}
