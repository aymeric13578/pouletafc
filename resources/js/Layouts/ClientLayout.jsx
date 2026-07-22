import { Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

const TABS = [
    { label: 'Mes commandes', href: () => route('client.orders.index'), match: 'client.orders.*' },
    { label: 'Mes adresses', href: () => route('client.addresses.index'), match: 'client.addresses.*' },
    { label: 'Mon profil', href: () => route('profile.edit'), match: 'profile.*' },
];

export default function ClientLayout({ title, children }) {
    return (
        <ShopLayout>
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="mb-8 animate-fade-in-up text-2xl font-extrabold text-gray-900">{title ?? 'Mon espace client'}</h1>

                <div className="flex flex-col gap-8 lg:flex-row">
                    <aside className="animate-fade-in-up lg:w-56 lg:shrink-0">
                        <nav className="flex gap-2 overflow-x-auto lg:flex-col lg:overflow-visible">
                            {TABS.map((tab) => (
                                <Link
                                    key={tab.label}
                                    href={tab.href()}
                                    className={`whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-medium transition-all duration-300 active:scale-95 ${
                                        route().current(tab.match)
                                            ? 'bg-brand-600 text-white shadow-sm'
                                            : 'text-gray-600 hover:bg-white hover:shadow-sm'
                                    }`}
                                >
                                    {tab.label}
                                </Link>
                            ))}
                        </nav>
                    </aside>

                    <div style={{ animationDelay: '80ms' }} className="flex-1 animate-fade-in-up">{children}</div>
                </div>
            </div>
        </ShopLayout>
    );
}
