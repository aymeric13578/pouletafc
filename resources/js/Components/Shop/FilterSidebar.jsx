import { useState } from 'react';
import { router } from '@inertiajs/react';

export default function FilterSidebar({ categories, filters }) {
    const [minPrice, setMinPrice] = useState(filters.min_price ?? '');
    const [maxPrice, setMaxPrice] = useState(filters.max_price ?? '');

    const apply = (overrides = {}) => {
        router.get(
            route('shop.catalog.index'),
            {
                ...filters,
                min_price: minPrice || undefined,
                max_price: maxPrice || undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const selectedCategory = filters.category ? String(filters.category) : '';

    return (
        <aside className="w-full shrink-0 animate-fade-in-up space-y-8 rounded-2xl bg-white p-5 shadow-md lg:w-64 lg:p-6">
            <div>
                <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-900">Catégories</h3>
                <ul className="space-y-1">
                    <li>
                        <button
                            type="button"
                            onClick={() => apply({ category: undefined })}
                            className={`w-full rounded-lg px-2.5 py-1.5 text-left text-sm transition-all duration-300 ${
                                selectedCategory === ''
                                    ? 'bg-brand-50 font-semibold text-brand-700'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-brand-700'
                            }`}
                        >
                            Toutes les catégories
                        </button>
                    </li>
                    {categories.map((category) => (
                        <li key={category.id}>
                            <button
                                type="button"
                                onClick={() => apply({ category: category.id })}
                                className={`w-full rounded-lg px-2.5 py-1.5 text-left text-sm transition-all duration-300 ${
                                    selectedCategory === String(category.id)
                                        ? 'bg-brand-50 font-semibold text-brand-700'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-brand-700'
                                }`}
                            >
                                {category.name}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>

            <div>
                <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-900">Prix (FCFA)</h3>
                <div className="flex items-center gap-2">
                    <input
                        type="number"
                        min="0"
                        value={minPrice}
                        onChange={(e) => setMinPrice(e.target.value)}
                        placeholder="Min"
                        className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                    />
                    <span className="text-gray-400">–</span>
                    <input
                        type="number"
                        min="0"
                        value={maxPrice}
                        onChange={(e) => setMaxPrice(e.target.value)}
                        placeholder="Max"
                        className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                    />
                </div>
                <button
                    type="button"
                    onClick={() => apply()}
                    className="mt-3 w-full rounded-full border border-brand-600 px-3 py-2 text-sm font-semibold text-brand-700 transition-all duration-300 hover:bg-brand-600 hover:text-white active:scale-95"
                >
                    Appliquer
                </button>
            </div>

            <div>
                <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-900">Trier par</h3>
                <select
                    value={filters.sort ?? ''}
                    onChange={(e) => apply({ sort: e.target.value || undefined })}
                    className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                >
                    <option value="">Popularité</option>
                    <option value="newest">Nouveautés</option>
                    <option value="price_asc">Prix croissant</option>
                    <option value="price_desc">Prix décroissant</option>
                </select>
            </div>
        </aside>
    );
}
