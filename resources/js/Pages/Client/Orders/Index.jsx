import { Head, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import StatusBadge from '@/Components/Shop/StatusBadge';
import Pagination from '@/Components/Shop/Pagination';
import EmptyState from '@/Components/Shop/EmptyState';
import { formatPrice } from '@/Utils/format';

export default function Index({ orders }) {
    return (
        <ClientLayout title="Mes commandes">
            <Head title="Mes commandes" />

            {orders.data.length === 0 ? (
                <EmptyState
                    title="Aucune commande pour le moment"
                    description="Vos commandes passées apparaîtront ici avec leur statut de livraison."
                    actionLabel="Découvrir la boutique"
                    actionHref={route('shop.catalog.index')}
                />
            ) : (
                <>
                    <div className="animate-fade-in-up overflow-hidden rounded-2xl bg-white shadow-md">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th className="px-5 py-3">Référence</th>
                                    <th className="px-5 py-3">Date</th>
                                    <th className="px-5 py-3">Total</th>
                                    <th className="px-5 py-3">Statut</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {orders.data.map((order) => (
                                    <tr key={order.ref} className="transition-colors duration-300 hover:bg-gray-50/60">
                                        <td className="px-5 py-4 font-medium text-gray-900">{order.ref}</td>
                                        <td className="px-5 py-4 text-gray-500">{order.date}</td>
                                        <td className="px-5 py-4 font-medium text-gray-900">{formatPrice(order.price)}</td>
                                        <td className="px-5 py-4"><StatusBadge status={order.status} /></td>
                                        <td className="px-5 py-4 text-right">
                                            <Link
                                                href={route('client.orders.show', order.ref)}
                                                className="font-semibold text-brand-700 transition-colors duration-300 hover:text-brand-800 hover:underline"
                                            >
                                                Détails
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination meta={orders} />
                </>
            )}
        </ClientLayout>
    );
}
