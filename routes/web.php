<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Admin\
{
    AgentController,
    UserController,
    DashboardController,
    CustomersController,
    AgentsController,
    MarchandsController,
    CommandesController,
    ProductsController,
    ShopController,
    CategoryController,
    SubcategoryController,
    SellerController,
    FinanceController,
    IndexController,
    OrderController,
    ArticleController,
    ClandoController,


};

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AgentAfcOrderController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });



Route::get('/deleteuseraccount', function () {    return view('welcome');
 })->name('welcome');

Route::get('/check-new-orders', [AgentAfcOrderController::class, 'checkNewOrders'])->name('check.new.order');
Route::get('/get-new-order', [AgentAfcOrderController::class, 'getNewOrder'])->name('get.new.order');

Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');



Route::get('validate/compte', 'App\Http\Controllers\Auth\ValidateCompte@index')->name('compte.validate');
Route::post('validate/compte', 'App\Http\Controllers\Auth\ValidateCompte@index')->name('compte.validate');
Route::get('validate/compte/create', 'App\Http\Controllers\Auth\ValidateCompte@create')->name('compte.validate.create');
Route::post('validate/compte/create', 'App\Http\Controllers\Auth\ValidateCompte@create')->name('compte.validate.create');

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/shop.php';

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');







// Route::group(['namespace' => 'App\Http\Controllers\Merchand'], function () {
//     Route::post('merchand-dashboard', 'IndexController@index')->name('merchanddashboard');
//     Route::get('merchand-dashboard', 'IndexController@index')->name('merchanddashboard');
//     Route::get('/merchand-list_products', [App\Http\Controllers\Merchand\ProductController::class, 'listProduct'])->name('merchandlistProduct');
//     Route::get('/merchand-add_products', [App\Http\Controllers\Merchand\ProductController::class, 'addProduct'])->name('merchandaddProduct');
//     Route::post('/merchand-store-product', [App\Http\Controllers\Merchand\ProductController::class, 'storeProduct'])->name('merchandstoreproduct');
//     Route::get('/merchand-category_products', [App\Http\Controllers\Merchand\ProductController::class, 'cathegoryProduct'])->name('merchandcathegoryProduct');
//     Route::get('/merchand-edit-product-img/{id}', [App\Http\Controllers\Merchand\ProductController::class, 'editProductImg'])->name('merchandeditproductimg');
//     Route::post('/merchand-update-product-img', [App\Http\Controllers\Merchand\ProductController::class, 'updateProductImg'])->name('merchandupdateproductimg');
//     Route::get('/merchand-edit-product/{id}', [App\Http\Controllers\Merchand\ProductController::class, 'editProduct'])->name('merchandeditproduct');
//     Route::post('/merchand-edition-product', [App\Http\Controllers\Merchand\ProductController::class, 'productEdit'])->name('merchandproductedition');
//     Route::post('/merchand-deletion-product', [App\Http\Controllers\Merchand\ProductController::class, 'productdelete'])->name('merchandproductdeletion');
//     Route::get('/merchand-customers_all', [App\Http\Controllers\Merchand\CustomerController::class, 'allCustomers'])->name('merchandallCustomers');
//     Route::get('/merchand-customers_details', [App\Http\Controllers\Merchand\CustomerController::class, 'customerDetail'])->name('merchandcustomerDetail');
// });



// Route::controller(App\Http\Controllers\Merchand\CategoryController::class)->group(function () {
//     Route::get('/merchand-category-list', 'categoryList')->name("merchandcategorylist");
//     Route::get('/merchand-add-category', 'addCategory')->name("merchandaddcategory");
//     Route::post('/merchand-store-category', 'storeCategory')->name('merchandstorecategory');
//     Route::get('/merchand-edit-category/{id}', 'editCategory')->name('merchandeditcategory');
//     Route::post('/merchand-update-category', 'updateCategory')->name('merchandupdatecategory');
//     Route::get('/merchand-delete-category/{id}', 'deleteCategory')->name('merchanddeletecategory');
//     Route::post('/merchand-edition', 'editCat')->name('merchandcatedition');
//     Route::post('/merchand-deletion', 'deleteCat')->name('merchandcatdeletion');
// });


// Route::controller(App\Http\Controllers\Merchand\ShopController::class)->group(function () {
//     Route::get('/merchand-shop-list', 'shopList')->name("merchandshoplist");
//     Route::get('/merchand-add-shop', 'addshop')->name("merchandaddshop");
//     Route::post('/merchand-store-shop', 'storeShop')->name('merchandstoreshop');
//     Route::post('/merchand-edition-shop', 'editShop')->name('merchandshopedition');
//     Route::post('/merchand-deletion-shop', 'deleteShop')->name('merchandshopdeletion');
// });

// Route::controller(OrderController::class)->group(function () {
//     Route::get('/order-list', 'orderList')->name("orderlist");
//     //Route::get('/orders-data','getOrdersData')->name('orders.data');
//     Route::get('/check-new-order','checkNewOrder')->name('check.new.order');

//     Route::get('/get-new-order',  'getNewOrder')->name('get.new.order');
// })->middleware('auth');

// Route::controller(App\Http\Controllers\Merchand\SubcategoryController::class)->group(function () {
//     Route::get('/merchand-sub-category-list', 'subCategoryList')->name("merchandsubcategorylist");
//     Route::get('/merchand-add-sub-category', 'addSubCategory')->name("merchandaddsubcategory");
//     Route::post('/merchand-store-sub-category', 'storeSubCategory')->name('merchandstoresubcategory');
//     Route::post('/merchand-edition-sub-category', 'editSubCat')->name('merchandsubcatedition');
//     Route::post('/merchand-deletion-sub-category', 'deleteSubCat')->name('merchandsubcatdeletion');
// });

// Route::controller(App\Http\Controllers\Merchand\FinanceController::class)->group(function () {
//     Route::get('/merchand-finance-list', 'financeList')->name("merchandfinancelist");
// });









// Route::group(['namespace' => 'App\Http\Controllers\Admin'], function () {

//     Route::post('dashboard', 'IndexController@index')->name('dashboard');

//     Route::get('parameters','ParametersController@index')->name('parameters.index');

//     Route::get('storeparameters','ParametersController@StoreParameters')->name('parameters.store');
//     Route::post('storeparameters','ParametersController@StoreParameters')->name('parameters.store');

// Route::post('/parameters/{id}/validate', 'ParametersController@validateParameter')->name('parameters.validate');
// Route::delete('/parameters/{id}', 'ParametersController@destroy')->name('parameters.destroy');


//     Route::get('changeStatus', 'StatusController@changeStatus')->name('status.change');
//     Route::post('changeStatus', 'StatusController@changeStatus')->name('status.change');

//     Route::get('changeRole', 'StatusController@changeRole')->name('role.change');
//     Route::post('changeRole', 'StatusController@changeRole')->name('role.change');



//     Route::get('dashboard', 'IndexController@index')->name('dashboard');
//     Route::get('logoutUser', 'IndexController@logoutUser')->name('logoutUser');
//     Route::get('register', 'RegisterController@registerform')->name('register');
//     Route::get('/list_products', [App\Http\Controllers\Admin\ProductController::class, 'listProduct'])->name('listProduct');
//     Route::get('/add_products', [App\Http\Controllers\Admin\ProductController::class, 'addProduct'])->name('addProduct');
//     Route::post('/admin/store-product', [App\Http\Controllers\Admin\ProductController::class, 'storeProduct'])->name('storeproduct');
//     Route::get('/category_products', [App\Http\Controllers\Admin\ProductController::class, 'cathegoryProduct'])->name('cathegoryProduct');
//     Route::get('/edit-product-img/{id}', [App\Http\Controllers\Admin\ProductController::class, 'editProductImg'])->name('editproductimg');
//     Route::post('/update-product-img', [App\Http\Controllers\Admin\ProductController::class, 'updateProductImg'])->name('updateproductimg');
//     Route::get('/edit-product/{id}', [App\Http\Controllers\Admin\ProductController::class, 'editProduct'])->name('editproduct');
//     Route::post('/edition-product', [App\Http\Controllers\Admin\ProductController::class, 'productEdit'])->name('productedition');
//     Route::post('/deletion-product', [App\Http\Controllers\Admin\ProductController::class, 'productdelete'])->name('productdeletion');
//     Route::get('/customers_all', [App\Http\Controllers\Admin\CustomerController::class, 'allCustomers'])->name('allCustomers');
//     Route::get('/customers_details', [App\Http\Controllers\Admin\CustomerController::class, 'customerDetail'])->name('customerDetail');
//     Route::get('/user_list', [App\Http\Controllers\Admin\UserController::class, 'listUser'])->name('listUser');
// })->middleware('auth');
// Route::controller(CategoryController::class)->group(function () {
//     Route::get('/category-list', 'categoryList')->name("categorylist");
//     Route::get('/add-category', 'addCategory')->name("addcategory");
//     Route::post('/store-category', 'storeCategory')->name('storecategory');
//     Route::get('/edit-category/{id}', 'editCategory')->name('editcategory');
//     Route::post('/update-category', 'updateCategory')->name('updatecategory');
//     Route::get('/delete-category/{id}', 'deleteCategory')->name('deletecategory');
//     Route::post('/edition', 'editCat')->name('catedition');
//     Route::post('/deletion', 'deleteCat')->name('catdeletion');
// })->middleware('auth');
// Route::controller(ShopController::class)->group(function () {
//     Route::get('/shop-list', 'shopList')->name("shoplist");
//     Route::get('/add-shop', 'addshop')->name("addshop");
//     Route::post('/store-shop', 'storeShop')->name('storeshop');
//     Route::post('/edition-shop', 'editShop')->name('shopedition');
//     Route::post('/deletion-shop', 'deleteShop')->name('shopdeletion');
// })->middleware('auth');
// Route::controller(SubCategoryController::class)->group(function () {
//     Route::get('/sub-category-list', 'subCategoryList')->name("subcategorylist");
//     Route::get('/add-sub-category', 'addSubCategory')->name("addsubcategory");
//     Route::post('/store-sub-category', 'storeSubCategory')->name('storesubcategory');
//     Route::post('/edition-sub-category', 'editSubCat')->name('subcatedition');
//     Route::post('/deletion-sub-category', 'deleteSubCat')->name('subcatdeletion');
// })->middleware('auth');

// Route::controller(SellerController::class)->group(function () {
//     Route::get('/seller-list', 'sellerList')->name("sellerlist");
//     Route::get('/add-seller', 'addSeller')->name("addseller");
//     Route::post('/store-seller', 'storeSeller')->name('storeseller');
//     Route::post('/edition-seller', 'editSeller')->name('selleredition');
//     Route::post('/deletion-seller', 'deleteSeller')->name('sellerdeletion');
// })->middleware('auth');
// Route::controller(AgentController::class)->group(function () {
//     Route::get('/agent-list', 'agentList')->name("agentlist");
//     Route::get('/agent-caution', 'agentCaution')->name("agentcaution");
//     Route::get('/add-agent', 'addAgent')->name("addagent");
//     Route::post('/store-agent', 'storeAgent')->name('storeagent');
//     Route::post('/edition-agent', 'editAgent')->name('agentedition');
//     Route::post('/deletion-agent', 'deleteAgent')->name('agentdeletion');
//     Route::get('/caution-list', 'cautionList')->name("cautionlist");



//     Route::post('/agents/credit', 'creditAgent')->name('agent.credit');
//    Route::get('/agents/credit-history/{id_user}', 'creditHistory')->name('agent.credit.history');

// })->middleware('auth');



// Route::controller(ClandoController::class)->group(function () {
//     Route::get('/clando-list', 'clandoList')->name('clandolist');

// Route::get('/clando/status/change/{id}/{status}',  'changeStatus')->name('clando.status.change');


// })->middleware('auth');














// Route::controller(ArticleController::class)->group(function () {

//     Route::get('/add-article', 'addArticle')->name("addArticle");
//     Route::post('/store-article', 'storeArticle')->name('storeArticle');
//     Route::get('/article-list', 'articleList')->name("listArticle");


// })->middleware('auth');

// Route::controller(FinanceController::class)->group(function () {
//     Route::get('/finance-list', 'financeList')->name("financelist");
// })->middleware('auth');


//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');








// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

//     Route::get('/admin', [DashboardController::class, 'index'])->name('admin.index');
// Route::get('/ListUsers', [UserController::class, 'index'])->name('admin.users');
// Route::get('/ListCustomers', [CustomersController::class, 'index'])->name('admin.customers');
// Route::get('/ListAgents', [AgentsController::class, 'index'])->name('admin.agents');
// Route::get('/ListMarchands', [MarchandsController::class, 'index'])->name('admin.marchands');

// Route::get('/ListCommandes', [CommandesController::class, 'index'])->name('admin.commandes');
// Route::get('/ListProducts', [ProductsController::class, 'index'])->name('admin.products');
// Route::get('/ListShops', [ShopsController::class, 'index'])->name('admin.shops');
// Route::get('/ListCategories', [CategoriesController::class, 'index'])->name('admin.categories');
// Route::get('/ListSubCategories', [SubCategoriesController::class, 'index'])->name('admin.subCategories');

// });

// require __DIR__.'/auth.php';
