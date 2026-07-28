<?php

use App\Http\Controllers\Client\AddressController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Shop\ArticleController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Boutique e-commerce (Poulet AFC)
|--------------------------------------------------------------------------
| Front-end public + espace client, en React/Inertia. Indépendant de l'API
| mobile (routes/api.php) et du back-office admin.
*/

Route::get('/', [HomeController::class, 'index'])->name('shop.home');

Route::get('/boutique', [CatalogController::class, 'index'])->name('shop.catalog.index');
Route::get('/produit/{slug}', [CatalogController::class, 'show'])->name('shop.catalog.show');

Route::get('/panier', [CartController::class, 'index'])->name('shop.cart.index');
Route::post('/panier/ajouter', [CartController::class, 'add'])->name('shop.cart.add');
Route::patch('/panier/{product}', [CartController::class, 'update'])->name('shop.cart.update');
Route::delete('/panier/{product}', [CartController::class, 'remove'])->name('shop.cart.remove');

Route::get('/actualites', [ArticleController::class, 'index'])->name('shop.articles.index');
Route::get('/actualites/{article}', [ArticleController::class, 'show'])->name('shop.articles.show');

Route::get('/suppression-compte', [PageController::class, 'deleteAccount'])->name('shop.pages.delete-account');
Route::post('/suppression-compte', [PageController::class, 'processDeleteAccount'])->name('shop.pages.delete-account.process');

Route::get('/cgv', [PageController::class, 'cgv'])->name('shop.pages.cgv');
Route::get('/faq', [PageController::class, 'faq'])->name('shop.pages.faq');
Route::get('/contact', [PageController::class, 'contact'])->name('shop.pages.contact');
Route::post('/contact', [PageController::class, 'sendContact'])->name('shop.pages.contact.send');
Route::post('/newsletter', [PageController::class, 'subscribeNewsletter'])->name('shop.newsletter.subscribe');

Route::middleware('auth')->group(function () {
    Route::get('/commande', [CheckoutController::class, 'index'])->name('shop.checkout.index');
    Route::post('/commande', [CheckoutController::class, 'store'])->name('shop.checkout.store');
    Route::get('/commande/confirmation/{ref}', [CheckoutController::class, 'confirmation'])->name('shop.checkout.confirmation');

    Route::get('/mon-compte/commandes', [OrderController::class, 'index'])->name('client.orders.index');
    Route::get('/mon-compte/commandes/{ref}', [OrderController::class, 'show'])->name('client.orders.show');

    Route::get('/mon-compte/adresses', [AddressController::class, 'index'])->name('client.addresses.index');
    Route::post('/mon-compte/adresses', [AddressController::class, 'store'])->name('client.addresses.store');
    Route::delete('/mon-compte/adresses/{address}', [AddressController::class, 'destroy'])->name('client.addresses.destroy');
});
