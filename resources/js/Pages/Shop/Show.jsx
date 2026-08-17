import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductCard from '@/Components/Shop/ProductCard';
import QuantityStepper from '@/Components/Shop/QuantityStepper';
import ShareProduct from '@/Components/Shop/ShareProduct';
import { formatPrice } from '@/Utils/format';

export default function Show({ product, related }) {
    const [activeImage, setActiveImage] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [selectedOption, setSelectedOption] = useState(product.options[0] ?? null);

    const addToCart = () => {
        router.post(
            route('shop.cart.add'),
            { product_id: product.id, quantity },
            { preserveScroll: true }
        );
    };

    return (
        <ShopLayout>
            <Head title={product.name} />

            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <nav className="mb-6 animate-fade-in-up text-sm text-gray-500">
                    <Link href={route('shop.catalog.index')} className="transition-colors duration-300 hover:text-brand-700">Boutique</Link>
                    {product.category && <span> / {product.category}</span>}
                    <span> / {product.name}</span>
                </nav>

                <div className="grid gap-10 lg:grid-cols-2">
                    <div className="animate-fade-in-up">
                        <div className="aspect-square overflow-hidden rounded-2xl bg-gray-100 shadow-sm">
                            <img
                                src={product.gallery[activeImage]}
                                alt={product.name}
                                className="h-full w-full object-cover transition-opacity duration-300"
                            />
                        </div>
                        {product.gallery.length > 1 && (
                            <div className="mt-3 flex gap-3">
                                {product.gallery.map((image, index) => (
                                    <button
                                        key={image + index}
                                        type="button"
                                        onClick={() => setActiveImage(index)}
                                        className={`h-16 w-16 overflow-hidden rounded-xl border-2 transition-all duration-300 ${
                                            activeImage === index
                                                ? 'border-brand-600 shadow-sm'
                                                : 'border-transparent opacity-70 hover:opacity-100'
                                        }`}
                                    >
                                        <img src={image} alt="" className="h-full w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div style={{ animationDelay: '100ms' }} className="animate-fade-in-up">
                        <h1 className="text-2xl font-extrabold text-gray-900">{product.name}</h1>
                        <p className="mt-3 text-3xl font-bold text-brand-700">{formatPrice(product.price)}</p>

                        {product.stock !== null && (
                            <p className={`mt-2 text-sm font-medium ${product.stock > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {product.stock > 0 ? `En stock (${product.stock} disponibles)` : 'Rupture de stock'}
                            </p>
                        )}

                        {product.description && (
                            <p className="mt-4 whitespace-pre-line text-sm leading-relaxed text-gray-600">
                                {product.description}
                            </p>
                        )}

                        {product.options.length > 0 && (
                            <div className="mt-6">
                                <h3 className="mb-2 text-sm font-semibold text-gray-900">Options</h3>
                                <div className="flex flex-wrap gap-2">
                                    {product.options.map((option) => (
                                        <button
                                            key={option}
                                            type="button"
                                            onClick={() => setSelectedOption(option)}
                                            className={`rounded-full border px-4 py-1.5 text-sm font-medium transition-all duration-300 active:scale-95 ${
                                                selectedOption === option
                                                    ? 'border-brand-600 bg-brand-600 text-white shadow-sm'
                                                    : 'border-gray-200 text-gray-600 hover:border-brand-400 hover:text-brand-700'
                                            }`}
                                        >
                                            {option}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="mt-8 flex items-center gap-4">
                            <QuantityStepper value={quantity} onChange={setQuantity} />
                            <button
                                type="button"
                                onClick={addToCart}
                                disabled={product.stock === 0}
                                className="flex-1 rounded-full bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-lg active:scale-95 disabled:opacity-50 disabled:active:scale-100"
                            >
                                Ajouter au panier
                            </button>
                        </div>

                        <ShareProduct product={product} />
                    </div>
                </div>

                {related.length > 0 && (
                    <section style={{ animationDelay: '150ms' }} className="mt-16 animate-fade-in-up">
                        <h2 className="text-xl font-bold text-gray-900">Produits similaires</h2>
                        <div className="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-4 sm:gap-4 lg:grid-cols-6">
                            {related.map((item, index) => (
                                <ProductCard key={item.id} product={item} index={index} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </ShopLayout>
    );
}
