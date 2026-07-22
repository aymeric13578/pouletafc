import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';

export default function Cgv() {
    return (
        <ShopLayout>
            <Head title="Conditions générales de vente" />

            <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <h1 className="animate-fade-in-up text-2xl font-extrabold text-gray-900">Conditions générales de vente</h1>

                <div className="prose mt-6 max-w-none animate-fade-in-up text-gray-600">
                    <p>
                        Les présentes conditions générales de vente régissent les commandes passées sur le site
                        Poulet AFC. En validant une commande, le client accepte sans réserve les conditions décrites
                        ci-dessous.
                    </p>
                    <h2>1. Commandes</h2>
                    <p>
                        Toute commande passée sur le site fait l'objet d'une confirmation par e-mail et/ou SMS
                        reprenant le détail des articles, le montant total et l'adresse de livraison.
                    </p>
                    <h2>2. Prix et paiement</h2>
                    <p>
                        Les prix sont indiqués en Francs CFA, toutes taxes comprises. Le paiement peut être effectué
                        par carte bancaire, Mobile Money (Orange/MTN) ou en espèces à la livraison.
                    </p>
                    <h2>3. Livraison</h2>
                    <p>
                        Les délais de livraison sont indiqués à titre indicatif au moment de la commande et peuvent
                        varier selon la zone géographique et la méthode de livraison choisie.
                    </p>
                    <h2>4. Rétractation et retours</h2>
                    <p>
                        En raison de la nature périssable des produits, aucun retour ne peut être accepté après
                        livraison, sauf produit non conforme signalé sous 24h.
                    </p>
                </div>
            </div>
        </ShopLayout>
    );
}
