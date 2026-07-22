import { Head, useForm, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/Shop/EmptyState';

export default function Index({ addresses }) {
    const { data, setData, post, processing, errors, reset } = useForm({ address: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('client.addresses.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    const destroy = (id) => {
        router.delete(route('client.addresses.destroy', id), { preserveScroll: true });
    };

    return (
        <ClientLayout title="Mes adresses">
            <Head title="Mes adresses" />

            <div className="space-y-6">
                <form onSubmit={submit} className="animate-fade-in-up rounded-2xl bg-white p-6 shadow-md">
                    <h2 className="mb-3 text-sm font-semibold text-gray-900">Ajouter une adresse</h2>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <input
                            type="text"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            placeholder="Quartier, rue, repère..."
                            className="flex-1 rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95 disabled:opacity-60 disabled:active:scale-100"
                        >
                            Ajouter
                        </button>
                    </div>
                    {errors.address && <p className="mt-2 text-xs text-red-600">{errors.address}</p>}
                </form>

                {addresses.length === 0 ? (
                    <EmptyState title="Aucune adresse enregistrée" description="Ajoutez une adresse pour accélérer vos prochaines commandes." />
                ) : (
                    <ul style={{ animationDelay: '80ms' }} className="animate-fade-in-up divide-y divide-gray-100 rounded-2xl bg-white shadow-md">
                        {addresses.map((address) => (
                            <li key={address.id} className="flex items-center justify-between px-5 py-4 text-sm transition-colors duration-300 hover:bg-gray-50/60">
                                <span className="text-gray-900">{address.address}</span>
                                <button
                                    type="button"
                                    onClick={() => destroy(address.id)}
                                    className="rounded-full px-2 py-1 font-medium text-red-500 transition-all duration-300 hover:bg-red-50 hover:text-red-700 active:scale-95"
                                >
                                    Supprimer
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </ClientLayout>
    );
}
