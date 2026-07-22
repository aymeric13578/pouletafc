import { Link, router } from '@inertiajs/react';
import { formatPrice } from '@/Utils/format';

export default function ProductCard({ product, index = 0 }) {
    const addToCart = (e) => {
        e.preventDefault();

        router.post(
            route('shop.cart.add'),
            { product_id: product.id, quantity: 1 },
            { preserveScroll: true }
        );
    };

    const delay = `${Math.min(index, 8) * 60}ms`;

    return (
        <Link
            href={route('shop.catalog.show', product.slug)}
            style={{ animationDelay: delay }}
            className="group flex animate-fade-in-up flex-col overflow-hidden rounded-2xl bg-white shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-brand-900/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
        >
            <div className="aspect-square w-full overflow-hidden bg-gray-100">
                <img
                    src={product.image_url}
                    alt={product.name}
                    loading="lazy"
                    className="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-110"
                />
            </div>

            <div className="flex flex-1 flex-col p-4 sm:p-5">
                <h3 className="line-clamp-2 flex-1 text-sm font-semibold text-gray-900">{product.name}</h3>

                <div className="mt-3 flex items-center justify-between gap-2">
                    <span className="text-base font-bold text-brand-700">{formatPrice(product.price)}</span>

                    <button
                        type="button"
                        onClick={addToCart}
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-500 text-white shadow-sm transition-all duration-300 hover:scale-110 hover:bg-accent-600 hover:shadow-md active:scale-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2"
                        aria-label="Ajouter au panier"
                        title="Ajout rapide au panier"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-5 w-5">
                            <path d="M7 4a1 1 0 00-1 1v1H4.5a1 1 0 000 2H5l1.68 8.39A2 2 0 008.64 18H17a1 1 0 000-2H8.64l-.2-1H17.5a2 2 0 001.94-1.51L20.7 8.8A1 1 0 0019.73 7.5H7.24L7 5H8a1 1 0 000-2H7z" />
                            <circle cx="9" cy="21" r="1.5" />
                            <circle cx="17" cy="21" r="1.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </Link>
    );
}
