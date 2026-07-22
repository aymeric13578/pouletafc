import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Show({ article, recent }) {
    return (
        <ShopLayout>
            <Head title={article.title} />

            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <Link href={route('shop.articles.index')} className="text-sm font-semibold text-brand-700 transition-colors duration-300 hover:text-brand-800 hover:underline">
                    ← Toutes les actualités
                </Link>

                <h1 className="mt-4 animate-fade-in-up text-3xl font-extrabold text-gray-900">{article.title}</h1>
                <p className="mt-2 text-sm text-gray-400">{article.date}</p>

                <div style={{ animationDelay: '80ms' }} className="mt-6 aspect-video w-full animate-fade-in-up overflow-hidden rounded-2xl bg-gray-100 shadow-sm">
                    <img src={article.image_url} alt={article.title} className="h-full w-full object-cover" />
                </div>

                <div className="prose mt-8 max-w-none whitespace-pre-line text-gray-700">
                    {article.description}
                </div>

                {recent.length > 0 && (
                    <div className="mt-12">
                        <h2 className="mb-4 text-lg font-bold text-gray-900">À lire aussi</h2>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {recent.map((item, index) => (
                                <Link
                                    key={item.id}
                                    href={route('shop.articles.show', item.id)}
                                    style={{ animationDelay: `${index * 60}ms` }}
                                    className="group animate-fade-in-up overflow-hidden rounded-2xl bg-white shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                                >
                                    <div className="aspect-video w-full overflow-hidden bg-gray-100">
                                        <img src={item.image_url} alt={item.title} className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                    </div>
                                    <div className="p-3 text-sm font-medium text-gray-900">{item.title}</div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
