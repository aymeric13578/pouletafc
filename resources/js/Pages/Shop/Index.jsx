import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductCard from '@/Components/Shop/ProductCard';
import ProductCardSkeleton from '@/Components/Shop/ProductCardSkeleton';
import FilterSidebar from '@/Components/Shop/FilterSidebar';
import Pagination from '@/Components/Shop/Pagination';
import EmptyState from '@/Components/Shop/EmptyState';

export default function Index({ products, categories, filters }) {
    const [isNavigating, setIsNavigating] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (event.detail.visit.method === 'get') {
                setIsNavigating(true);
            }
        });
        const removeFinish = router.on('finish', () => setIsNavigating(false));
        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    return (
        <ShopLayout>
            <Head title="Boutique" />

            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="mb-8 flex animate-fade-in-up flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-extrabold text-gray-900">
                            La <span className="text-brand-600">boutique</span>
                        </h1>
                        {filters.q && (
                            <p className="mt-1 text-sm text-gray-500">
                                Résultats pour « {filters.q} » — {products.total} produit(s)
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex flex-col gap-8 lg:flex-row">
                    <FilterSidebar categories={categories} filters={filters} />

                    <div className="flex-1">
                        {isNavigating ? (
                            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 sm:gap-4 xl:grid-cols-6">
                                {Array.from({ length: 8 }).map((_, index) => (
                                    <ProductCardSkeleton key={index} />
                                ))}
                            </div>
                        ) : products.data.length === 0 ? (
                            <EmptyState
                                title="Aucun produit trouvé"
                                description="Essayez d'élargir votre recherche ou de changer de catégorie."
                                actionLabel="Réinitialiser les filtres"
                                actionHref={route('shop.catalog.index')}
                            />
                        ) : (
                            <>
                                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 sm:gap-4 xl:grid-cols-6">
                                    {products.data.map((product, index) => (
                                        <ProductCard key={product.id} product={product} index={index} />
                                    ))}
                                </div>
                                <Pagination meta={products} />
                            </>
                        )}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
