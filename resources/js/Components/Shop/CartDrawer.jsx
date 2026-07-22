import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Link, router, usePage } from '@inertiajs/react';
import { formatPrice } from '@/Utils/format';
import QuantityStepper from '@/Components/Shop/QuantityStepper';

export default function CartDrawer({ open, onClose }) {
    const { cart } = usePage().props;
    const items = cart?.items ?? [];
    const subtotal = cart?.subtotal ?? 0;

    const updateQuantity = (productId, quantity) => {
        router.patch(route('shop.cart.update', productId), { quantity }, { preserveScroll: true, preserveState: true });
    };

    const removeItem = (productId) => {
        router.delete(route('shop.cart.remove', productId), { preserveScroll: true, preserveState: true });
    };

    return (
        <Transition show={open} as={Fragment}>
            <Dialog onClose={onClose} className="relative z-50">
                <Transition.Child
                    as={Fragment}
                    enter="ease-in-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in-out duration-300"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-900/40" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-hidden">
                    <div className="absolute inset-0 overflow-hidden">
                        <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                            <Transition.Child
                                as={Fragment}
                                enter="transform transition ease-in-out duration-300"
                                enterFrom="translate-x-full"
                                enterTo="translate-x-0"
                                leave="transform transition ease-in-out duration-300"
                                leaveFrom="translate-x-0"
                                leaveTo="translate-x-full"
                            >
                                <Dialog.Panel className="pointer-events-auto w-screen max-w-md">
                                    <div className="flex h-full flex-col overflow-y-auto bg-white shadow-2xl">
                                        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                                            <Dialog.Title className="text-lg font-bold text-gray-900">
                                                Votre panier
                                            </Dialog.Title>
                                            <button
                                                type="button"
                                                onClick={onClose}
                                                className="flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition-all duration-300 hover:bg-gray-100 hover:text-gray-600 active:scale-90"
                                                aria-label="Fermer"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>

                                        <div className="flex-1 px-6 py-4">
                                            {items.length === 0 ? (
                                                <div className="mt-10 flex flex-col items-center text-center">
                                                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-3xl">
                                                        🛒
                                                    </div>
                                                    <p className="text-sm text-gray-500">
                                                        Votre panier est vide pour le moment.
                                                    </p>
                                                </div>
                                            ) : (
                                                <ul className="divide-y divide-gray-100">
                                                    {items.map((item, index) => (
                                                        <li
                                                            key={item.product.id}
                                                            style={{ animationDelay: `${Math.min(index, 6) * 60}ms` }}
                                                            className="flex animate-fade-in-up gap-4 py-4"
                                                        >
                                                            <img
                                                                src={item.product.image_url}
                                                                alt={item.product.name}
                                                                className="h-16 w-16 flex-shrink-0 rounded-xl object-cover shadow-sm"
                                                            />
                                                            <div className="flex flex-1 flex-col">
                                                                <div className="flex justify-between text-sm font-medium text-gray-900">
                                                                    <span className="line-clamp-2 pr-2">{item.product.name}</span>
                                                                    <span className="whitespace-nowrap">{formatPrice(item.line_total)}</span>
                                                                </div>
                                                                <div className="mt-2 flex items-center justify-between">
                                                                    <QuantityStepper
                                                                        size="sm"
                                                                        value={item.quantity}
                                                                        onChange={(qty) => updateQuantity(item.product.id, qty)}
                                                                    />
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeItem(item.product.id)}
                                                                        className="rounded-full px-2 py-1 text-xs font-medium text-red-500 transition-all duration-300 hover:bg-red-50 hover:text-red-700 active:scale-95"
                                                                    >
                                                                        Retirer
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>

                                        {items.length > 0 && (
                                            <div className="animate-fade-in-up border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                                                <div className="flex justify-between text-base font-semibold text-gray-900">
                                                    <span>Sous-total</span>
                                                    <span>{formatPrice(subtotal)}</span>
                                                </div>
                                                <p className="mt-1 text-xs text-gray-500">
                                                    Frais de livraison calculés à l'étape suivante.
                                                </p>
                                                <div className="mt-4 flex flex-col gap-2">
                                                    <Link
                                                        href={route('shop.checkout.index')}
                                                        className="w-full rounded-full bg-brand-600 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95"
                                                    >
                                                        Passer la commande
                                                    </Link>
                                                    <Link
                                                        href={route('shop.cart.index')}
                                                        onClick={onClose}
                                                        className="w-full rounded-full border border-gray-200 bg-white px-4 py-3 text-center text-sm font-semibold text-gray-700 transition-all duration-300 hover:border-gray-300 hover:bg-gray-50 active:scale-95"
                                                    >
                                                        Voir le panier
                                                    </Link>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </Dialog.Panel>
                            </Transition.Child>
                        </div>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}
