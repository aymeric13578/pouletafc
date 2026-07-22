import { Head, Link, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import QuantityStepper from '@/Components/Shop/QuantityStepper';
import EmptyState from '@/Components/Shop/EmptyState';
import { formatPrice } from '@/Utils/format';

const DELIVERY_ESTIMATE = 1000;

export default function Index({ items, subtotal }) {
    const updateQuantity = (productId, quantity) => {
        router.patch(route('shop.cart.update', productId), { quantity }, { preserveScroll: true });
    };

    const removeItem = (productId) => {
        router.delete(route('shop.cart.remove', productId), { preserveScroll: true });
    };

    return (
        <ShopLayout>
            <Head title="Mon panier" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="mb-8 animate-fade-in-up text-2xl font-extrabold text-gray-900">
                    Mon <span className="text-brand-600">panier</span>
                </h1>

                {items.length === 0 ? (
                    <EmptyState
                        title="Votre panier est vide"
                        description="Parcourez la boutique pour trouver votre bonheur."
                        actionLabel="Voir la boutique"
                        actionHref={route('shop.catalog.index')}
                    />
                ) : (
                    <div className="grid gap-8 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            <ul className="animate-fade-in-up divide-y divide-gray-100 rounded-2xl bg-white shadow-md">
                                {items.map((item, index) => (
                                    <li
                                        key={item.product.id}
                                        style={{ animationDelay: `${Math.min(index, 8) * 60}ms` }}
                                        className="flex animate-fade-in-up flex-col gap-4 p-5 transition-colors duration-300 hover:bg-gray-50/60 sm:flex-row sm:items-center"
                                    >
                                        <img
                                            src={item.product.image_url}
                                            alt={item.product.name}
                                            className="h-20 w-20 rounded-xl object-cover shadow-sm"
                                        />
                                        <div className="flex-1">
                                            <Link
                                                href={route('shop.catalog.show', item.product.slug)}
                                                className="font-semibold text-gray-900 transition-colors duration-300 hover:text-brand-700"
                                            >
                                                {item.product.name}
                                            </Link>
                                            <p className="mt-1 text-sm text-gray-500">{formatPrice(item.product.price)} / unité</p>
                                        </div>
                                        <QuantityStepper
                                            value={item.quantity}
                                            onChange={(qty) => updateQuantity(item.product.id, qty)}
                                        />
                                        <div className="w-28 text-right font-semibold text-gray-900">
                                            {formatPrice(item.line_total)}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeItem(item.product.id)}
                                            className="rounded-full px-2 py-1 text-sm font-medium text-red-500 transition-all duration-300 hover:bg-red-50 hover:text-red-700 active:scale-95"
                                        >
                                            Supprimer
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div style={{ animationDelay: '100ms' }} className="h-fit animate-fade-in-up rounded-2xl bg-white p-6 shadow-md">
                            <h2 className="text-lg font-bold text-gray-900">Récapitulatif</h2>
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex justify-between text-gray-600">
                                    <dt>Sous-total</dt>
                                    <dd>{formatPrice(subtotal)}</dd>
                                </div>
                                <div className="flex justify-between text-gray-600">
                                    <dt>Livraison estimée</dt>
                                    <dd>{formatPrice(DELIVERY_ESTIMATE)}</dd>
                                </div>
                                <div className="flex justify-between border-t border-gray-100 pt-2 text-base font-bold text-gray-900">
                                    <dt>Total estimé</dt>
                                    <dd>{formatPrice(subtotal + DELIVERY_ESTIMATE)}</dd>
                                </div>
                            </dl>
                            <Link
                                href={route('shop.checkout.index')}
                                className="mt-6 block w-full rounded-full bg-brand-600 px-4 py-3 text-center text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95"
                            >
                                Passer la commande
                            </Link>
                            <Link
                                href={route('shop.catalog.index')}
                                className="mt-3 block w-full rounded-full border border-gray-200 px-4 py-3 text-center text-sm font-semibold text-gray-700 transition-all duration-300 hover:bg-gray-50 active:scale-95"
                            >
                                Continuer mes achats
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
