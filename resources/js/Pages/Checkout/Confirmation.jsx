import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import StatusBadge from '@/Components/Shop/StatusBadge';
import { formatPrice } from '@/Utils/format';

export default function Confirmation({ order }) {
    return (
        <ShopLayout>
            <Head title="Commande confirmée" />

            <div className="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <div className="mx-auto mb-6 flex h-16 w-16 animate-scale-in items-center justify-center rounded-full bg-green-100 text-3xl shadow-sm">
                    ✅
                </div>
                <h1 className="animate-fade-in-up text-2xl font-extrabold text-gray-900">Merci pour votre commande !</h1>
                <p style={{ animationDelay: '60ms' }} className="mt-2 animate-fade-in-up text-gray-500">
                    Votre commande <span className="font-semibold text-gray-900">{order.ref}</span> a bien été enregistrée.
                </p>

                <div style={{ animationDelay: '120ms' }} className="mt-8 animate-fade-in-up rounded-2xl bg-white p-6 text-left shadow-md">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-500">Statut</span>
                        <StatusBadge status={order.status} />
                    </div>

                    <ul className="mt-4 divide-y divide-gray-100">
                        {order.items.map((item, index) => (
                            <li key={index} className="flex justify-between py-2 text-sm">
                                <span>{item.quantity} × {item.name}</span>
                                <span className="font-medium">{formatPrice(item.amount * item.quantity)}</span>
                            </li>
                        ))}
                    </ul>

                    <dl className="mt-4 space-y-1 border-t border-gray-100 pt-3 text-sm">
                        <div className="flex justify-between text-gray-600">
                            <dt>Sous-total</dt>
                            <dd>{formatPrice(order.panier_price)}</dd>
                        </div>
                        <div className="flex justify-between text-gray-600">
                            <dt>Livraison</dt>
                            <dd>{formatPrice(order.delivery_fees)}</dd>
                        </div>
                        <div className="flex justify-between text-base font-bold text-gray-900">
                            <dt>Total</dt>
                            <dd>{formatPrice(order.price)}</dd>
                        </div>
                    </dl>

                    <p className="mt-4 text-sm text-gray-500">Livraison à : {order.address}</p>
                </div>

                <div style={{ animationDelay: '180ms' }} className="mt-8 flex animate-fade-in-up justify-center gap-3">
                    <Link
                        href={route('client.orders.index')}
                        className="rounded-full bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95"
                    >
                        Suivre mes commandes
                    </Link>
                    <Link
                        href={route('shop.catalog.index')}
                        className="rounded-full border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 transition-all duration-300 hover:bg-gray-50 active:scale-95"
                    >
                        Continuer mes achats
                    </Link>
                </div>
            </div>
        </ShopLayout>
    );
}
