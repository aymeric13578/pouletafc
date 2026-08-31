<?php

use App\Http\Controllers\Client\AddressController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Shop\ArticleController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\MobileAppController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ShareImageController;
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

/*
| Adresse propre d'une catégorie, pour qu'elle puisse être partagée.
|
| Le filtre du catalogue passe par « ?category=12 » : un identifiant ne dit rien
| de ce qu'on ouvre, et le lien ne survit pas à une renumérotation. La route par
| slug est celle qu'on colle dans une conversation.
*/
Route::get('/boutique/categorie/{slug}', [CatalogController::class, 'categorie'])->name('shop.catalog.category');

Route::get('/produit/{slug}', [CatalogController::class, 'show'])->name('shop.catalog.show');

/*
| Images d'aperçu du partage, redimensionnées et compressées à la volée.
| L'extension .jpg n'est pas décorative : certains robots d'aperçu ignorent une
| adresse d'image qui n'en porte pas.
*/
Route::get('/apercu/produit/{slug}.jpg', [ShareImageController::class, 'produit'])->name('shop.share.image.product');
Route::get('/apercu/categorie/{slug}.jpg', [ShareImageController::class, 'categorie'])->name('shop.share.image.category');

Route::get('/panier', [CartController::class, 'index'])->name('shop.cart.index');
Route::post('/panier/ajouter', [CartController::class, 'add'])->name('shop.cart.add');
Route::patch('/panier/{product}', [CartController::class, 'update'])->name('shop.cart.update');
Route::delete('/panier/{product}', [CartController::class, 'remove'])->name('shop.cart.remove');

Route::get('/actualites', [ArticleController::class, 'index'])->name('shop.articles.index');
Route::get('/actualites/{article}', [ArticleController::class, 'show'])->name('shop.articles.show');

/*
| Mur des commandes affiché en télévision, en accès libre : l'écran tourne en
| continu dans le local, sans clavier ni session ouverte.
|
| Conséquence assumée : la page expose noms, téléphones et adresses de clients à
| qui connaît l'URL. Si le besoin d'un garde-fou se présente, le plus simple sans
| identifiants est de déplacer la route derrière un segment secret.
*/
Route::get('/commandes', [\App\Http\Controllers\Admin\OrderBoardController::class, 'index'])->name('orders.board');
Route::get('/commandes/flux', [\App\Http\Controllers\Admin\OrderBoardController::class, 'feed'])->name('orders.board.feed');
Route::post('/commandes/{order}/statut', [\App\Http\Controllers\Admin\OrderBoardController::class, 'updateStatus'])->name('orders.board.status');
Route::post('/commandes/{order}/paiement', [\App\Http\Controllers\Admin\OrderBoardController::class, 'updatePayment'])->name('orders.board.payment');
// Correction du panier au poids réel, le tarif au kilo venant de la grille active.
Route::post('/commandes/{order}/panier', [\App\Http\Controllers\Admin\OrderBoardController::class, 'updateCart'])->name('orders.board.cart');
// Alignement du montant sur le contenu réel du panier, décidé au comptoir.
/*
| Changement du lieu de livraison, depuis le mur comme depuis la carte.
|
| L'adresse était figée à la création : corriger un quartier mal compris au
| téléphone obligeait à annuler la commande et à la ressaisir.
*/
Route::post('/commandes/{order}/lieu', [\App\Http\Controllers\Admin\OrderBoardController::class, 'changerLeLieu'])->name('orders.board.place');

Route::post('/commandes/{order}/recalcul', [\App\Http\Controllers\Admin\OrderBoardController::class, 'recalculerLeTotal'])->name('orders.board.recompute');

/*
| Correction du panier d'une commande par le comptoir.
|
| Un article en rupture ou commandé par erreur obligeait jusqu'ici à tout
| annuler et à ressaisir, en perdant l'historique et l'agent déjà attribué.
*/
Route::post('/commandes/{order}/articles', [\App\Http\Controllers\Admin\OrderBoardController::class, 'ajouterArticle'])->name('orders.board.item.add');
Route::delete('/commandes/{order}/articles/{item}', [\App\Http\Controllers\Admin\OrderBoardController::class, 'retirerArticle'])->name('orders.board.item.remove');
Route::get('/commandes/{order}/complements', [\App\Http\Controllers\Admin\OrderBoardController::class, 'complementsProposes'])->name('orders.board.complements');

/*
| Carte des courses clando, en accès libre pour la même raison que le mur des
| commandes : l'écran tourne dans le local sans session ouverte.
|
| Même réserve, et elle pèse plus lourd ici : la page expose la position en direct
| des agents en plus des coordonnées des clients. Si un garde-fou devient
| nécessaire, le plus simple reste de déplacer la route derrière un segment secret.
*/
Route::get('/clando', [\App\Http\Controllers\Admin\ClandoBoardController::class, 'index'])->name('clando.board');
Route::get('/clando/flux', [\App\Http\Controllers\Admin\ClandoBoardController::class, 'feed'])->name('clando.board.feed');
Route::post('/clando/{course}/agent', [\App\Http\Controllers\Admin\ClandoBoardController::class, 'assign'])->name('clando.board.assign');
// Classement des agents candidats pour cette course, calculé à l'ouverture du menu d'attribution.
Route::get('/clando/{course}/agents-classes', [\App\Http\Controllers\Admin\ClandoBoardController::class, 'classerAgents'])->name('clando.board.agents-classes');
// Annulation d'une course, motif obligatoire.
Route::post('/clando/{course}/lieu', [\App\Http\Controllers\Admin\ClandoBoardController::class, 'changerLeLieu'])->name('clando.board.place');
Route::post('/clando/{course}/annulation', [\App\Http\Controllers\Admin\ClandoBoardController::class, 'annuler'])->name('clando.board.cancel');
// Bouton panique (clando.dart) : accusé de réception d'une alerte depuis le mur.
Route::post('/clando/alerte/{incident}/acquitter', [\App\Http\Controllers\Admin\ClandoBoardController::class, 'acquitterAlerte'])->name('clando.board.alerte.acquitter');

/*
| Carte des livraisons : activité des agents et points de livraison, atteinte
| depuis un bouton du mur des commandes. Même accès libre que les deux écrans
| précédents, pour la même raison.
*/
Route::get('/commandes/carte', [\App\Http\Controllers\Admin\OrderMapController::class, 'index'])->name('orders.map');
Route::get('/commandes/carte/flux', [\App\Http\Controllers\Admin\OrderMapController::class, 'feed'])->name('orders.map.feed');
Route::post('/commandes/carte/{order}/agent', [\App\Http\Controllers\Admin\OrderMapController::class, 'assign'])->name('orders.map.assign');
// Classement des agents candidats pour cette commande, calculé à l'ouverture du menu d'attribution.
Route::get('/commandes/carte/{order}/agents-classes', [\App\Http\Controllers\Admin\OrderMapController::class, 'classerAgents'])->name('orders.map.agents-classes');

/*
| Sondé par les écrans "mur" (commandes/clando/carte) pendant qu'ils
| affichent un QR de déverrouillage — voir App\Support\KioskLock.
*/
Route::get('/deverrouillage/{token}/statut', [\App\Http\Controllers\Admin\KioskLockController::class, 'statut'])->name('kiosk.status');

Route::get('/download-app', [MobileAppController::class, 'show'])->name('shop.app.show');
Route::get('/download-app/clando.apk', [MobileAppController::class, 'apk'])->name('shop.app.android.apk');

/*
| Application agent, partagée par lien depuis l'écran Agents.
|
| Pas de page de présentation : elle ne s'adresse pas au public. L'URL est
| volontairement courte et lisible, un livreur devant parfois la recopier à la
| main depuis un message.
*/
Route::get('/agent.apk', [MobileAppController::class, 'agentApk'])->name('app.agent.apk');

// Ancienne URL francophone : conservée en redirection au cas où elle aurait été
// partagée, l'URL de référence communiquée est /download-app.
Route::redirect('/application-mobile', '/download-app', 301);

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
