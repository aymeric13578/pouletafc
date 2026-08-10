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
    
    // Reprise du suivi après fermeture de l'application.
    //
    // Répond « où cet utilisateur doit-il être ramené » : la course ou la
    // livraison en cours, qu'il soit le client ou l'agent, avec de quoi rouvrir
    // l'écran quitté. Sans elle, fermer l'application faisait perdre le suivi
    // d'une course qui continuait pourtant côté serveur.
    Route::get('getCourseEnCours', 'SuiviController@getCourseEnCours');
    Route::post('getCourseEnCours', 'SuiviController@getCourseEnCours');

    // geolocalisation


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
      
      //note
      
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
