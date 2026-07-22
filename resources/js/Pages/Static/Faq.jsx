import { useState } from 'react';
import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

const FAQS = [
    {
        question: 'Quels sont les délais de livraison ?',
        answer: 'La livraison standard est effectuée sous 24 à 48h. La livraison express est disponible sous 2h dans certaines zones urbaines.',
    },
    {
        question: 'Quels moyens de paiement acceptez-vous ?',
        answer: 'Nous acceptons la carte bancaire, le Mobile Money (Orange/MTN) ainsi que le paiement à la livraison.',
    },
    {
        question: 'Puis-je suivre ma commande ?',
        answer: 'Oui, rendez-vous dans votre espace client, rubrique « Mes commandes », pour suivre le statut de votre livraison.',
    },
    {
        question: 'Comment modifier ou annuler une commande ?',
        answer: "Contactez notre service client via la page Contact dès que possible : une commande déjà en préparation ne peut plus être modifiée.",
    },
];

export default function Faq() {
    const [open, setOpen] = useState(0);

    return (
        <ShopLayout>
            <Head title="FAQ" />

            <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 className="animate-fade-in-up text-2xl font-extrabold text-gray-900">
                    Questions <span className="text-brand-600">fréquentes</span>
                </h1>

                <div className="mt-6 animate-fade-in-up divide-y divide-gray-100 rounded-2xl bg-white shadow-md">
                    {FAQS.map((faq, index) => (
                        <div key={faq.question} className="p-5 transition-colors duration-300 hover:bg-gray-50/60">
                            <button
                                type="button"
                                onClick={() => setOpen(open === index ? -1 : index)}
                                className="flex w-full items-center justify-between text-left text-sm font-semibold text-gray-900"
                            >
                                {faq.question}
                                <span className={`ml-4 shrink-0 text-lg text-brand-600 transition-transform duration-300 ${open === index ? 'rotate-45' : 'rotate-0'}`}>
                                    +
                                </span>
                            </button>
                            <div className={`grid transition-all duration-300 ease-out ${open === index ? 'mt-3 grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'}`}>
                                <p className="overflow-hidden text-sm leading-relaxed text-gray-600">{faq.answer}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </ShopLayout>
    );
}
