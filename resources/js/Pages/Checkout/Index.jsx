import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatPrice } from '@/Utils/format';

const STEPS = ['Livraison', 'Paiement', 'Récapitulatif'];

export default function Index({ items, subtotal, addresses, deliveryMethods, paymentMethods, user }) {
    const [step, setStep] = useState(0);

    const { data, setData, post, processing, errors, transform } = useForm({
        address_id: addresses[0]?.id ?? '',
        new_address: '',
        phone: user.phone ?? '',
        delivery_method: 'standard',
        payment_method: 'cash_on_delivery',
    });

    transform((formData) => ({
        ...formData,
        address_id: !formData.address_id || formData.address_id === 'new' ? null : formData.address_id,
    }));

    const deliveryFee = deliveryMethods[data.delivery_method]?.fee ?? 0;
    const total = subtotal + deliveryFee;

    const useNewAddress = addresses.length === 0 || data.address_id === 'new';

    const next = () => setStep((s) => Math.min(s + 1, STEPS.length - 1));
    const back = () => setStep((s) => Math.max(s - 1, 0));

    const submit = (e) => {
        e.preventDefault();
        post(route('shop.checkout.store'));
    };

    return (
        <ShopLayout>
            <Head title="Commande" />

            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="mb-6 animate-fade-in-up text-2xl font-extrabold text-gray-900">
                    Finaliser ma <span className="text-brand-600">commande</span>
                </h1>

                <div className="mb-8 flex animate-fade-in-up items-center gap-2">
                    {STEPS.map((label, index) => (
                        <div key={label} className="flex flex-1 items-center gap-2">
                            <div
                                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-all duration-300 ${
                                    index <= step ? 'scale-105 bg-brand-600 text-white shadow-sm' : 'bg-gray-200 text-gray-500'
                                }`}
                            >
                                {index + 1}
                            </div>
                            <span className={`text-sm font-medium transition-colors duration-300 ${index <= step ? 'text-gray-900' : 'text-gray-400'}`}>
                                {label}
                            </span>
                            {index < STEPS.length - 1 && (
                                <div className="h-px flex-1 bg-gray-200">
                                    <div
                                        className={`h-px bg-brand-600 transition-all duration-500 ${index < step ? 'w-full' : 'w-0'}`}
                                    />
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                <form onSubmit={submit} className="grid gap-8 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {step === 0 && (
                            <div className="animate-fade-in-up rounded-2xl bg-white p-6 shadow-md">
                                <h2 className="mb-4 text-lg font-bold text-gray-900">Informations de livraison</h2>

                                {addresses.length > 0 && (
                                    <div className="mb-4 space-y-2">
                                        {addresses.map((address) => (
                                            <label
                                                key={address.id}
                                                className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm transition-all duration-300 ${
                                                    String(data.address_id) === String(address.id)
                                                        ? 'border-brand-500 bg-brand-50/60 shadow-sm'
                                                        : 'border-gray-200 hover:border-brand-300'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="address_id"
                                                    checked={String(data.address_id) === String(address.id)}
                                                    onChange={() => setData('address_id', address.id)}
                                                    className="text-brand-600 focus:ring-brand-500"
                                                />
                                                {address.address}
                                            </label>
                                        ))}
                                        <label
                                            className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm transition-all duration-300 ${
                                                data.address_id === 'new'
                                                    ? 'border-brand-500 bg-brand-50/60 shadow-sm'
                                                    : 'border-gray-200 hover:border-brand-300'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="address_id"
                                                checked={data.address_id === 'new'}
                                                onChange={() => setData('address_id', 'new')}
                                                className="text-brand-600 focus:ring-brand-500"
                                            />
                                            Utiliser une nouvelle adresse
                                        </label>
                                    </div>
                                )}

                                {useNewAddress && (
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Adresse de livraison</label>
                                        <input
                                            type="text"
                                            value={data.new_address}
                                            onChange={(e) => setData('new_address', e.target.value)}
                                            placeholder="Quartier, rue, repère..."
                                            className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                                        />
                                        {errors.new_address && <p className="mt-1 text-xs text-red-600">{errors.new_address}</p>}
                                    </div>
                                )}

                                <div className="mt-4">
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Téléphone</label>
                                    <input
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                                    />
                                    {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                                </div>

                                <h3 className="mb-3 mt-6 text-sm font-semibold text-gray-900">Méthode de livraison</h3>
                                <div className="space-y-2">
                                    {Object.entries(deliveryMethods).map(([key, method]) => (
                                        <label
                                            key={key}
                                            className={`flex cursor-pointer items-center justify-between rounded-xl border p-3 text-sm transition-all duration-300 ${
                                                data.delivery_method === key
                                                    ? 'border-brand-500 bg-brand-50/60 shadow-sm'
                                                    : 'border-gray-200 hover:border-brand-300'
                                            }`}
                                        >
                                            <span className="flex items-center gap-3">
                                                <input
                                                    type="radio"
                                                    name="delivery_method"
                                                    checked={data.delivery_method === key}
                                                    onChange={() => setData('delivery_method', key)}
                                                    className="text-brand-600 focus:ring-brand-500"
                                                />
                                                <span>
                                                    <span className="block font-medium text-gray-900">{method.label}</span>
                                                    <span className="block text-xs text-gray-500">{method.description}</span>
                                                </span>
                                            </span>
                                            <span className="font-semibold text-gray-900">{formatPrice(method.fee)}</span>
                                        </label>
                                    ))}
                                </div>

                                <button
                                    type="button"
                                    onClick={next}
                                    className="mt-6 w-full rounded-full bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95"
                                >
                                    Continuer vers le paiement
                                </button>
                            </div>
                        )}

                        {step === 1 && (
                            <div className="animate-fade-in-up rounded-2xl bg-white p-6 shadow-md">
                                <h2 className="mb-4 text-lg font-bold text-gray-900">Méthode de paiement</h2>
                                <div className="space-y-3">
                                    {Object.entries(paymentMethods).map(([key, label]) => (
                                        <label
                                            key={key}
                                            className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm transition-all duration-300 ${
                                                data.payment_method === key
                                                    ? 'border-brand-500 bg-brand-50/60 shadow-sm'
                                                    : 'border-gray-200 hover:border-brand-300'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                checked={data.payment_method === key}
                                                onChange={() => setData('payment_method', key)}
                                                className="text-brand-600 focus:ring-brand-500"
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>

                                {data.payment_method === 'card' && (
                                    <div className="mt-4 grid animate-fade-in-up gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2">
                                        <input type="text" placeholder="Numéro de carte" className="col-span-2 rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500" />
                                        <input type="text" placeholder="MM / AA" className="rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500" />
                                        <input type="text" placeholder="CVC" className="rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500" />
                                    </div>
                                )}

                                {data.payment_method === 'mobile_money' && (
                                    <div className="mt-4 animate-fade-in-up rounded-xl bg-gray-50 p-4">
                                        <input type="tel" placeholder="Numéro Mobile Money (Orange/MTN)" className="w-full rounded-lg border-gray-200 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500" />
                                    </div>
                                )}

                                <p className="mt-4 text-xs text-gray-400">
                                    Paiement présenté à titre de démonstration — aucune transaction réelle n'est effectuée ici.
                                </p>

                                <div className="mt-6 flex gap-3">
                                    <button
                                        type="button"
                                        onClick={back}
                                        className="rounded-full border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 transition-all duration-300 hover:bg-gray-50 active:scale-95"
                                    >
                                        Retour
                                    </button>
                                    <button
                                        type="button"
                                        onClick={next}
                                        className="flex-1 rounded-full bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95"
                                    >
                                        Voir le récapitulatif
                                    </button>
                                </div>
                            </div>
                        )}

                        {step === 2 && (
                            <div className="animate-fade-in-up rounded-2xl bg-white p-6 shadow-md">
                                <h2 className="mb-4 text-lg font-bold text-gray-900">Récapitulatif de la commande</h2>
                                <ul className="divide-y divide-gray-100">
                                    {items.map((item) => (
                                        <li key={item.product.id} className="flex justify-between py-3 text-sm">
                                            <span>{item.quantity} × {item.product.name}</span>
                                            <span className="font-medium">{formatPrice(item.line_total)}</span>
                                        </li>
                                    ))}
                                </ul>

                                <div className="mt-4 flex gap-3">
                                    <button
                                        type="button"
                                        onClick={back}
                                        className="rounded-full border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 transition-all duration-300 hover:bg-gray-50 active:scale-95"
                                    >
                                        Retour
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="flex-1 rounded-full bg-accent-500 px-6 py-3 text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-accent-600 hover:shadow-md active:scale-95 disabled:opacity-60 disabled:active:scale-100"
                                    >
                                        Confirmer et payer {formatPrice(total)}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>

                    <div style={{ animationDelay: '100ms' }} className="h-fit animate-fade-in-up rounded-2xl bg-white p-6 shadow-md">
                        <h2 className="text-lg font-bold text-gray-900">Total</h2>
                        <dl className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between text-gray-600">
                                <dt>Sous-total</dt>
                                <dd>{formatPrice(subtotal)}</dd>
                            </div>
                            <div className="flex justify-between text-gray-600">
                                <dt>Livraison</dt>
                                <dd>{formatPrice(deliveryFee)}</dd>
                            </div>
                            <div className="flex justify-between border-t border-gray-100 pt-2 text-base font-bold text-gray-900">
                                <dt>Total</dt>
                                <dd>{formatPrice(total)}</dd>
                            </div>
                        </dl>
                    </div>
                </form>
            </div>
        </ShopLayout>
    );
}
