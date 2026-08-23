<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::group(['namespace' => 'App\Http\Controllers\API','prefix'=>'v1.0'], function () {
    
    
    
    //orange 
    Route::get('testfunction','PaymentController@testfunction')->name('testfunction');
    //orange 
Route::get('operator/orange/OrangePhase1','PaymentController@OrangePhase1')->name('OrangePhase1.get');
Route::post('operator/orange/OrangePhase1','PaymentController@OrangePhase1')->name('OrangePhase1.post');



Route::post('operator/orange/OrangePhaseUser','PaymentController@OrangePhaseUser')->name('OrangePhaseUser.post');
Route::get('operator/orange/OrangePhaseUser','PaymentController@OrangePhaseUser')->name('OrangePhaseUser.get');


Route::get('operator/orange/OrangePhase2','PaymentController@OrangePhase2')->name('OrangePhase2.get');
Route::post('operator/orange/OrangePhase2','PaymentController@OrangePhase2')->name('OrangePhase2.post');

Route::get('operator/orange/OrangePhase3/{ref}','PaymentController@OrangePhase3')->name('OrangePhase3.get');
Route::post('operator/orange/OrangePhase3/{ref}','PaymentController@OrangePhase3')->name('OrangePhase3.post');


    //statut du paiement
Route::get('verifiedOrangePaymentStatus','PaymentController@verifiedOrangePaymentStatus')->name('verifiedOrangePaymentStatus.get');
Route::post('verifiedOrangePaymentStatus','PaymentController@verifiedOrangePaymentStatus')->name('verifiedOrangePaymentStatus.post');


    
    //notification
    
    
     Route::get('getNotification', 'NotificationController@getNotification');
     Route::post('getNotification', 'NotificationController@getNotification');
    
    
    //articles
    Route::get('getArticles', 'ArticleController@getArticles');
    Route::post('getArticles', 'ArticleController@getArticles');
    
    //parameters
    
    
    Route::get('getParameters', 'ParametersController@getSuccessParameter');
     Route::post('getParameters', 'ParametersController@getSuccessParameter');
    
    //products
    Route::get('getAllProducts', 'ProductsController@getAllProducts');
    Route::post('storeProduct', 'ProductsController@storeProduct');
    Route::post('getProductsByCategory', 'ProductsController@getProductsByCategory');
    Route::get('getProductsByCategory', 'ProductsController@getProductsByCategory');

    //categories

    Route::get('getAllCategory', 'CategoryController@getAllCategory');
    Route::post('storeCategory', 'CategoryController@storeCategory');
    
    //finance 
    
        Route::post('financeAgent', 'FinanceController@getfinanceAgent');
            
        Route::get('financeAgent', 'FinanceController@getfinanceAgent');
    
    // Espace boutique dans l'application, pendant du module « Ma boutique » du
    // tableau de bord. Un marchand connecté depuis son téléphone n'avait aucun
    // moyen de voir sa vitrine ni ses commandes.
    Route::get('getMyShop', 'MaBoutiqueController@getMyShop');
    Route::post('getMyShop', 'MaBoutiqueController@getMyShop');
    Route::post('updateMyShop', 'MaBoutiqueController@updateMyShop');
    Route::get('getMyShopProducts', 'MaBoutiqueController@getMyShopProducts');
    Route::get('getMyShopOrders', 'MaBoutiqueController@getMyShopOrders');
    Route::get('getShopCategories', 'MaBoutiqueController@getCategories');
    // Onglet « Boutiques » de l'application : ces deux routes n'existaient pas,
    // l'écran répondait donc « aucune boutique » à tous les marchands.
    Route::get('verifiedShopUser', 'MaBoutiqueController@verifiedShopUser');
    Route::post('verifiedShopUser', 'MaBoutiqueController@verifiedShopUser');
    Route::get('getShopStats', 'MaBoutiqueController@getShopStats');
    Route::post('saveMyShopProduct', 'MaBoutiqueController@saveMyShopProduct');

    // Demande de course coursier depuis l'application cliente.
    //
    // L'écran poste vers cette route depuis des mois, mais elle n'avait jamais
    // été écrite : le serveur répondait « méthode non autorisée » et aucune
    // demande de coursier n'aboutissait.
    Route::post('storeDeliveryOrder', 'CoursierController@storeDeliveryOrder');

    // Demandes de coursier en attente, pour l'application agent.
    //
    // storeDeliveryOrder écrit dans order_details comme une commande boutique
    // ordinaire (delivery_type='coursier', id_cart=null) : sans cette route,
    // rien ne les distinguait des commandes boutique dans les listes que
    // l'agent interroge déjà (getAllOrder/getAllWithoutSellerOrder).
    Route::get('getPendingCoursierRequests', 'CoursierController@getPendingCoursierRequests');

    // Objectifs et primes — application agent (lecture des campagnes en
    // cours + engagement sur une option). Pas d'écran dashboard pour créer
    // une campagne pour l'instant : tant qu'aucune ligne n'existe dans
    // goal_campaigns, getGoalCampaigns renvoie une liste vide.
    Route::get('getGoalCampaigns', 'GoalController@getGoalCampaigns');
    Route::post('enrollGoalCampaign', 'GoalController@enrollGoalCampaign');

    // Reprise du suivi après fermeture de l'application.
    //
    // Répond « où cet utilisateur doit-il être ramené » : la course ou la
    // livraison en cours, qu'il soit le client ou l'agent, avec de quoi rouvrir
    // l'écran quitté. Sans elle, fermer l'application faisait perdre le suivi
    // d'une course qui continuait pourtant côté serveur.
    Route::get('getCourseEnCours', 'SuiviController@getCourseEnCours');
    Route::post('getCourseEnCours', 'SuiviController@getCourseEnCours');
    // Ce que le client a en cours, pour être prévenu dès qu'un agent le prend.
    Route::get('getSuivisClient', 'SuiviController@getSuivisClient');
    Route::post('getSuivisClient', 'SuiviController@getSuivisClient');

    // geolocalisation


       // Position d'un agent, envoyée en continu tant qu'il est en service.
       // Sans elle, la position n'était écrite qu'au démarrage de la journée et
       // les agents restaient figés sur les cartes.
       Route::get('updateAgentPosition', 'GeolocalisationController@updateAgentPosition');
       Route::post('updateAgentPosition', 'GeolocalisationController@updateAgentPosition');

       Route::post('getLocationAgent', 'GeolocalisationController@getLocationAgent');
    
       Route::get('getLocationAgent', 'GeolocalisationController@getLocationAgent');

       // Lieux de livraison : alimente la recherche d'adresse du panier.
       Route::get('getLocations', 'GeolocalisationController@getLocations');

       Route::post('getLocations', 'GeolocalisationController@getLocations');

       // Saisie des quartiers et lieux depuis l'application coursier.
       // L'application émet ces appels en GET avec des paramètres d'URL : les
       // deux verbes sont exposés, comme partout ailleurs dans cette API.
       Route::get('getQuarters', 'GeolocalisationController@getQuarters');
       Route::post('getQuarters', 'GeolocalisationController@getQuarters');

       Route::get('createQuarter', 'GeolocalisationController@createQuarter');
       Route::post('createQuarter', 'GeolocalisationController@createQuarter');

       Route::get('createLocation', 'GeolocalisationController@createLocation');
       Route::post('createLocation', 'GeolocalisationController@createLocation');

       Route::get('getPlacesHistory', 'GeolocalisationController@getPlacesHistory');
       Route::post('getPlacesHistory', 'GeolocalisationController@getPlacesHistory');

    //agent
    
     Route::get('updatePositionAgent', 'ClandoController@updatePositionAgent');
      Route::post('updatePositionAgent', 'ClandoController@updatePositionAgent');
      
     /*
    | Compléments : ce qu'on propose en plus d'un produit.
    |
    | Un complément est un produit marqué comme tel et rattaché aux plats qui le
    | proposent. L'application interroge ces routes à l'ajout d'un produit, puis
    | avant la validation du panier.
    */
    Route::get('getProductComplements', 'ComplementController@getProductComplements');
    Route::post('getProductComplements', 'ComplementController@getProductComplements');
    Route::get('getCartComplements', 'ComplementController@getCartComplements');
    Route::post('getCartComplements', 'ComplementController@getCartComplements');

    /*
    | Commentaires sur une prestation.
    |
    | Distincts des notes : on peut commenter sans juger, plusieurs fois, et
    | après coup.
    */
    Route::post('postCommentaire', 'CommentaireController@postCommentaire');
    Route::get('getCommentairesPrestation', 'CommentaireController@getCommentairesPrestation');
    Route::post('getCommentairesPrestation', 'CommentaireController@getCommentairesPrestation');
    Route::get('getCommentairesAgent', 'CommentaireController@getCommentairesAgent');
    Route::post('getCommentairesAgent', 'CommentaireController@getCommentairesAgent');

     //note
      
            // Détail des appréciations : ce que l'agent lit dans son application,
            // et l'administrateur dans le tableau de bord.
            Route::get('getNotesAgent', 'NoteController@getNotesAgent');
            Route::post('getNotesAgent', 'NoteController@getNotesAgent');
            // Barème en vigueur, pour que les applications affichent les mêmes
            // émoticônes et les mêmes points que le tableau de bord.
            Route::get('getBaremeNotation', 'NoteController@getBaremeNotation');
            Route::post('getBaremeNotation', 'NoteController@getBaremeNotation');
            Route::get('getAgentNote', 'NoteController@getAgentNote');
            Route::post('getAgentNote', 'NoteController@getAgentNote');
      
      Route::get('makeNote', 'NoteController@makeNote');
       Route::post('makeNote', 'NoteController@makeNote');
       
        Route::post('makeNote', 'NoteController@makeNote');
        
        
       Route::get('verifiedStatusClandoNote', 'NoteController@verifiedStatusClandoNote');
       Route::post('verifiedStatusClandoNote', 'NoteController@verifiedStatusClandoNote');
       
       Route::get('verifiedStatusOrderNote', 'NoteController@verifiedStatusOrderNote');
       Route::post('verifiedStatusOrderNote', 'NoteController@verifiedStatusOrderNote');
      
    
    //clando
    
     Route::get('getClandoHistoryResearch', 'ClandoController@getClandoHistoryResearch');
     Route::post('getClandoHistoryResearch', 'ClandoController@getClandoHistoryResearch');
    
    Route::get('storeHistory', 'ClandoController@storeHistory');
    Route::post('storeHistory', 'ClandoController@storeHistory');
 
 
 Route::get('takePosition', 'ClandoController@takePosition');
  Route::post('takePosition', 'ClandoController@takePosition');
 
   Route::get('historiqueClandoUser', 'ClandoController@historiqueClandoUser');
    Route::post('historiqueClandoUser', 'ClandoController@historiqueClandoUser');
 
 
 
 
 
 
 
  Route::get('updateClandoStatus', 'ClandoController@updateClandoStatus');
 Route::post('updateClandoStatus', 'ClandoController@updateClandoStatus');
 
 
 Route::get('getAllClando', 'ClandoController@getAllClando');
 Route::post('getAllClando', 'ClandoController@getAllClando');
 
 Route::get('getClandoWithoutAgent', 'ClandoController@getClandoWithoutAgent');
 Route::post('getClandoWithoutAgent', 'ClandoController@getClandoWithoutAgent');
    
             Route::get('getClandoHistorique', 'ClandoController@getClandoHistorique');
             Route::post('getClandoHistorique', 'ClandoController@getClandoHistorique');
             
             
             
            Route::get('getClandoAgent', 'ClandoController@getClandoAgent');
            Route::post('getClandoAgent', 'ClandoController@getClandoAgent');
    
         Route::post('insertclando', 'ClandoController@Insertclando');
           
         Route::get('insertclando', 'ClandoController@Insertclando');
         
         
          Route::post('getclando', 'ClandoController@getClando');
          Route::get('getclando', 'ClandoController@getClando');
          
          Route::get('takeClandoCommand', 'ClandoController@takeClandoCommand');
          Route::post('takeClandoCommand', 'ClandoController@takeClandoCommand');
    

    /*
    | Annulation : constater, prononcer, et proposer des motifs.
    |
    | « disponibiliteCommande » est interrogée en boucle par la fenêtre ouverte
    | sur le téléphone de l'agent. Rien ne la refermait quand le comptoir
    | annulait : l'agent partait livrer une commande qui n'existait plus.
    */
    Route::get('disponibiliteCommande', 'AnnulationController@disponibilite');
    Route::post('disponibiliteCommande', 'AnnulationController@disponibilite');
    Route::get('annulerCommande', 'AnnulationController@annuler');
    Route::post('annulerCommande', 'AnnulationController@annuler');
    Route::get('motifsAnnulation', 'AnnulationController@motifs');
    Route::get('eligibiliteAnnulation', 'AnnulationController@eligibilite');
    Route::post('eligibiliteAnnulation', 'AnnulationController@eligibilite');

        Route::post('getActiveCommand', 'ClandoController@getActiveCommand');
        Route::get('getActiveCommand', 'ClandoController@getActiveCommand');
        
        
         Route::get('declinCommand', 'ClandoController@declinCommand');
         Route::post('declinCommand', 'ClandoController@declinCommand');
         
         
          Route::get('declinCommandAfterTake', 'ClandoController@declinCommandAfterTake');
          Route::post('declinCommandAfterTake', 'ClandoController@declinCommandAfterTake');
          
          
          
         Route::post('mapAftertake', 'ClandoController@mapAftertake');
          Route::get('mapAftertake', 'ClandoController@mapAftertake');
          
          
            Route::get('terminatedCourse', 'ClandoController@terminatedCourse');
            Route::post('terminatedCourse', 'ClandoController@terminatedCourse');


    //subcategories
    Route::get('getAllSubCategory', 'SubcategoriesController@getAllSubCategory');

    //country
    Route::get('getAllCountry', 'CountriesController@getAllCountry');

    //user
    
    
    Route::get('changePasswordByOtp', 'UserController@changePasswordByOtp');
    Route::post('changePasswordByOtp', 'UserController@changePasswordByOtp');
    
    
    
    
    Route::get('verifyOtpChangePassword', 'UserController@verifyOtpChangePassword');
     Route::post('verifyOtpChangePassword', 'UserController@verifyOtpChangePassword');
    
    
    
     Route::get('sendOtpCode', 'UserController@sendOtpCode');
     Route::post('sendOtpCode', 'UserController@sendOtpCode');
    
     Route::get('changePassword', 'UserController@changePassword');
     Route::post('changePassword', 'UserController@changePassword');
     
     
      Route::get('updateUser', 'UserController@updateUser');
     Route::post('updateUser', 'UserController@updateUser');

     Route::get('deleteUser', 'UserController@deleteUser');
     Route::post('deleteUser', 'UserController@deleteUser');

     
    
    Route::get('updateDeliveryPosition', 'UserController@updateDeliveryPosition');
    Route::post('updateDeliveryPosition', 'UserController@updateDeliveryPosition');
    
    
    Route::get('getInfoUser', 'UserController@getInfoUser');
    Route::post('getInfoUser', 'UserController@getInfoUser');
    
    
    
    Route::get('takeDay', 'UserController@takeDay');
    Route::post('takeDay', 'UserController@takeDay');

     Route::get('takeDayDesactive', 'UserController@takeDayDesactive');
     Route::post('takeDayDesactive', 'UserController@takeDayDesactive');
                    // register
    Route::post('register', 'UserController@register');
    Route::get('register', 'UserController@register');

                    //login
    Route::post('login', 'UserController@login');
    Route::get('login', 'UserController@login');
    Route::post('loginDelivery', 'UserController@loginDelivery');
    Route::get('loginDelivery', 'UserController@loginDelivery');
    
    
     Route::post('loginEmployee', 'UserController@loginEmployee');
     Route::get('loginEmployee', 'UserController@loginEmployee');

                    //validate compte 

        
    Route::post('validateCompte', 'UserController@validateCompte');
    Route::get('validateCompte', 'UserController@validateCompte');




 //shops
 Route::get('getAllshops', 'ShopsController@getAllShops');
 Route::post('getAllshops', 'ShopsController@getAllShops');

 Route::post('addShop', 'ShopsController@addShop'); 

 //Shop Products
 Route::get('getShopProduct', 'ShopProductController@getShopProduct');
 
 

 //Saler Shop
 Route::get('getSalerShop', 'SalerShopController@getSalerShop');

  //Panier

  Route::get('viewCart', 'CartController@viewCart');
  Route::post('addToCartAndView', 'CartController@addToCartAndView');
  Route::get('addToCartAndView', 'CartController@addToCartAndView');
  Route::get('DeleteCart', 'CartController@deleteCart');
  Route::get('DeleteCartItem', 'CartController@deleteProductCart');
  Route::get('getPaymentMethod', 'CartController@getPaymentMethod');
 
  Route::get('removeItem', 'CartController@removeItem');
 
 Route::get('updateItem', 'CartController@updateItem');

  Route::post('updateItem', 'CartController@updateItem');
  
  //finance
  
  Route::get('getfinanceAgent', 'FinanceController@getfinanceAgent');
    Route::post('getfinanceAgent', 'FinanceController@getfinanceAgent');
  
 
 //order
 
 Route::get('updateOrderStatus', 'OrderController@updateOrderStatus');
 Route::post('updateOrderStatus', 'OrderController@updateOrderStatus');
 
 
  Route::get('getCommandAgent', 'OrderController@getCommandAgent');
   Route::post('getCommandAgent', 'OrderController@getCommandAgent');
   
   
 
          Route::get('declinCommandAfterTakeOrder', 'OrderController@declinCommandAfterTakeOrder');
          Route::post('declinCommandAfterTakeOrder', 'OrderController@declinCommandAfterTakeOrder');
 
             Route::get('terminatedCourseOrder', 'OrderController@terminatedCourseOrder');
            Route::post('terminatedCourseOrder', 'OrderController@terminatedCourseOrder');
  
         Route::post('mapAftertakeOrder', 'OrderController@mapAftertakeOrder');
          Route::get('mapAftertakeOrder', 'OrderController@mapAftertakeOrder');
 
 
 Route::get('updatePositionAgentOrder', 'OrderController@updatePositionAgentOrder');
      Route::post('updatePositionAgentOrder', 'OrderController@updatePositionAgentOrder');
 
         Route::get('declinOrderCommand', 'OrderController@declinOrderCommand');
         Route::post('declinOrderCommand', 'OrderController@declinOrderCommand');
 
 
 
 
          Route::get('takeOrderCommand', 'OrderController@takeOrderCommand');
          Route::post('takeOrderCommand', 'OrderController@takeOrderClandoCommand');
 
 
  Route::post('createclandoorder', 'OrderController@createclandoorder');
  Route::get('createclandoorder', 'OrderController@createclandoorder');
 
 
 Route::post('createOrder', 'OrderController@CreateOrder');
Route::get('createOrder', 'OrderController@CreateOrder');

/*
| Validation d'un panier composé sur le téléphone.
|
| Le panier n'existe plus côté serveur avant d'être commandé : il arrive ici d'un
| bloc, et le panier, ses articles et la commande sont écrits dans une seule
| transaction. « createOrder » reste en service pour les versions déjà
| installées, qui construisent encore le panier au fur et à mesure.
*/
Route::post('validerPanier', 'PanierValideController');
Route::get('validerPanier', 'PanierValideController');

Route::get('getOrder', 'OrderController@getOrder');
Route::get('getUserOrder', 'OrderController@getUserOrder');

Route::get('getAllOrder', 'OrderController@getAllOrder');
Route::post('getAllOrder', 'OrderController@getAllOrder');


Route::get('insertPosition', 'OrderController@insertPosition');
Route::post('insertPosition', 'OrderController@insertPosition');

Route::post('getSellerOrder', 'OrderController@getSellerOrder');
Route::get('getSellerOrder', 'OrderController@getSellerOrder');




Route::get('takeOrderBySeller', 'OrderController@takeOrderBySeller');
Route::post('takeOrderBySeller', 'OrderController@takeOrderBySeller');



Route::get('getAllOrderWithoutCondition', 'OrderController@getAllOrderWithoutCondition');
Route::post('getAllOrderWithoutCondition', 'OrderController@getAllOrderWithoutCondition');

Route::get('getAllWithoutSellerOrder', 'OrderController@getAllWithoutSellerOrder');
Route::post('getAllWithoutSellerOrder', 'OrderController@getAllWithoutSellerOrder');
});
