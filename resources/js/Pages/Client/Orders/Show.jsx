import { Head, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import StatusBadge from '@/Components/Shop/StatusBadge';
import { formatPrice } from '@/Utils/format';

export default function Show({ order }) {
    return (
        <ClientLayout title={`Commande ${order.ref}`}>
            <Head title={`Commande ${order.ref}`} />

            <div className="animate-fade-in-up rounded-2xl bg-white p-6 shadow-md">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-gray-500">Référence</p>
                        <p className="text-lg font-bold text-gray-900">{order.ref}</p>
                    </div>
                    <StatusBadge status={order.status} />
                </div>

                <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-xs uppercase text-gray-400">Date</dt>
                        <dd className="text-sm text-gray-900">{order.date}</dd>
                    </div>
                    <div>
                        <dt className="text-xs uppercase text-gray-400">Livraison</dt>
                        <dd className="text-sm text-gray-900">{order.delivery_type}</dd>
                    </div>
                    <div>
                        <dt className="text-xs uppercase text-gray-400">Paiement</dt>
                        <dd className="text-sm text-gray-900">{order.payment_method} ({order.status_paiement})</dd>
                    </div>
                    <div>
                        <dt className="text-xs uppercase text-gray-400">Téléphone</dt>
                        <dd className="text-sm text-gray-900">{order.phone_customer}</dd>
                    </div>
                    <div className="sm:col-span-2">
                        <dt className="text-xs uppercase text-gray-400">Adresse</dt>
                        <dd className="text-sm text-gray-900">{order.address}</dd>
                    </div>
                </dl>

                <h2 className="mb-3 mt-8 text-sm font-semibold text-gray-900">Articles</h2>
                <ul className="divide-y divide-gray-100 rounded-xl border border-gray-100">
                    {order.items.map((item, index) => (
                        <li key={index} className="flex justify-between px-4 py-3 text-sm">
                            <span>{item.quantity} × {item.name}</span>
                            <span className="font-medium">{formatPrice(item.amount * item.quantity)}</span>
                        </li>
                    ))}
                </ul>

                <div className="mt-4 flex justify-between text-base font-bold text-gray-900">
                    <span>Total</span>
                    <span>{formatPrice(order.price)}</span>
                </div>

                <Link
                    href={route('client.orders.index')}
                    className="mt-6 inline-block text-sm font-semibold text-brand-700 transition-colors duration-300 hover:text-brand-800 hover:underline"
                >
                    ← Retour à mes commandes
                </Link>
            </div>
        </ClientLayout>
    );
}
