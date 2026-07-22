import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Header from '@/Components/Shop/Header';
import Footer from '@/Components/Shop/Footer';

export default function ShopLayout({ children }) {
    const { flash } = usePage().props;
    const [message, setMessage] = useState(null);

    useEffect(() => {
        if (flash?.success) {
            setMessage({ type: 'success', text: flash.success });
        } else if (flash?.error) {
            setMessage({ type: 'error', text: flash.error });
        } else {
            return;
        }

        const timeout = setTimeout(() => setMessage(null), 4000);
        return () => clearTimeout(timeout);
    }, [flash]);

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <Header />

            {message && (
                <div
                    className={`fixed left-1/2 top-20 z-50 -translate-x-1/2 animate-scale-in rounded-full px-5 py-2.5 text-sm font-semibold shadow-xl ${
                        message.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
                    }`}
                >
                    {message.text}
                </div>
            )}

            <main className="flex-1">{children}</main>

            <Footer />
        </div>
    );
}
