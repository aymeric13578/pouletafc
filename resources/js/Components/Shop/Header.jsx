import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import Dropdown from '@/Components/Dropdown';
import CartDrawer from '@/Components/Shop/CartDrawer';

const NAV_LINKS = [
    { label: 'Accueil', href: '/' },
    { label: 'Boutique', href: route('shop.catalog.index') },
    { label: 'Blog', href: route('shop.articles.index') },
    { label: 'Contact', href: route('shop.pages.contact') },
];

export default function Header() {
    const { auth, cart } = usePage().props;
    const [cartOpen, setCartOpen] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [cartBump, setCartBump] = useState(false);
    const previousCount = useRef(cart?.count ?? 0);

    useEffect(() => {
        const count = cart?.count ?? 0;
        if (count > previousCount.current) {
            setCartBump(true);
            const timeout = setTimeout(() => setCartBump(false), 500);
            previousCount.current = count;
            return () => clearTimeout(timeout);
        }
        previousCount.current = count;
    }, [cart?.count]);

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('shop.catalog.index'), { q: query }, { preserveState: true });
    };

    return (
        <>
            <header className="sticky top-0 z-30 animate-fade-in-up border-b border-gray-100 bg-white/95 backdrop-blur-md">
                <div className="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <Link href="/" className="flex shrink-0 items-center gap-2 transition-transform duration-300 active:scale-95">
                        <img src="/images/logo.png" alt="Poulet AFC" className="h-10 w-10 rounded-full object-cover shadow-sm" />
                        <span className="hidden text-lg font-extrabold text-brand-700 sm:block">Poulet AFC</span>
                    </Link>

                    <nav className="hidden items-center gap-6 lg:flex">
                        {NAV_LINKS.map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                className="relative text-sm font-medium text-gray-600 transition-colors duration-300 after:absolute after:-bottom-1 after:left-0 after:h-0.5 after:w-0 after:bg-brand-600 after:transition-all after:duration-300 hover:text-brand-700 hover:after:w-full"
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>

                    <form onSubmit={submitSearch} className="hidden flex-1 max-w-lg md:block">
                        <div className="relative">
                            <input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Rechercher un produit…"
                                className="w-full rounded-full border-gray-200 bg-gray-50 py-2 pl-4 pr-10 text-sm transition-all duration-300 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500"
                            />
                            <button type="submit" className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors duration-300 hover:text-brand-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                    <circle cx="11" cy="11" r="7" />
                                    <path strokeLinecap="round" d="M21 21l-4.35-4.35" />
                                </svg>
                            </button>
                        </div>
                    </form>

                    <div className="ml-auto flex items-center gap-1 sm:gap-2">
                        <button
                            type="button"
                            onClick={() => setCartOpen(true)}
                            className="relative flex h-10 w-10 items-center justify-center rounded-full text-gray-600 transition-all duration-300 hover:bg-gray-100 active:scale-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            aria-label="Ouvrir le panier"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                className={`h-6 w-6 ${cartBump ? 'animate-bump' : ''}`}
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13L5.4 5M7 13l-1.5 6h11.6M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />
                            </svg>
                            {cart?.count > 0 && (
                                <span
                                    className={`absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-accent-500 px-1 text-[11px] font-bold text-white shadow-sm transition-transform duration-300 ${
                                        cartBump ? 'scale-125' : 'scale-100'
                                    }`}
                                >
                                    {cart.count}
                                </span>
                            )}
                        </button>

                        {auth?.user ? (
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="flex h-10 w-10 items-center justify-center rounded-full text-gray-600 transition-all duration-300 hover:bg-gray-100 active:scale-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                        aria-label="Mon compte"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-6 w-6">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0" />
                                        </svg>
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content>
                                    <div className="border-b border-gray-100 px-4 py-2 text-sm text-gray-500">
                                        Bonjour, {auth.user.name}
                                    </div>
                                    <Dropdown.Link href={route('client.orders.index')}>Mes commandes</Dropdown.Link>
                                    <Dropdown.Link href={route('client.addresses.index')}>Mes adresses</Dropdown.Link>
                                    <Dropdown.Link href={route('profile.edit')}>Mon profil</Dropdown.Link>
                                    <Dropdown.Link href={route('logout')} method="post" as="button">
                                        Déconnexion
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        ) : (
                            <a
                                href={route('login')}
                                className="hidden shrink-0 items-center rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95 sm:flex"
                            >
                                Connexion
                            </a>
                        )}

                        <button
                            type="button"
                            onClick={() => setMobileOpen((v) => !v)}
                            className="flex h-10 w-10 items-center justify-center rounded-full text-gray-600 transition-all duration-300 hover:bg-gray-100 active:scale-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:hidden"
                            aria-label="Menu"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                {mobileOpen && (
                    <div className="animate-fade-in-up border-t border-gray-100 px-4 py-3 lg:hidden">
                        <form onSubmit={submitSearch} className="mb-3">
                            <input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Rechercher un produit…"
                                className="w-full rounded-full border-gray-200 bg-gray-50 py-2 px-4 text-sm transition-all duration-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500"
                            />
                        </form>
                        <div className="flex flex-col gap-1">
                            {NAV_LINKS.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.href}
                                    className="rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors duration-300 hover:bg-brand-50 hover:text-brand-700"
                                >
                                    {link.label}
                                </Link>
                            ))}
                            {!auth?.user && (
                                <a
                                    href={route('login')}
                                    className="mt-2 rounded-full bg-brand-600 px-3 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 active:scale-95 sm:hidden"
                                >
                                    Connexion
                                </a>
                            )}
                        </div>
                    </div>
                )}
            </header>

            <CartDrawer open={cartOpen} onClose={() => setCartOpen(false)} />
        </>
    );
}
