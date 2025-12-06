<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Agency\OrderController as AgencyOrderController; // 管理画面用
use App\Http\Controllers\Admin\OrderController as AdminOrderController; // 管理画面用
use App\Http\Controllers\Admin\UserController as AdminUserController; 
use App\Http\Controllers\Agency\UserController as AgencynUserController; 
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MerchantController as AdminMerchantController;
use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Agency\DashboardController as AgencyDashboardController;
use App\Http\Controllers\Agency\MerchantController as AgencyMerchantController; 
use App\Http\Controllers\Agency\AuthController as AgencyAuthController;

use App\Http\Controllers\OrderController as UserOrderController; // ユーザー用
use App\Http\Controllers\MerchantController as UserMerchantController; // ユーザー用
use App\Http\Controllers\UserController;
use App\Models\Agency;

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

Route::get('/', function () {
    return view('welcome');
});

// 注文画面
Route::get('/order/list', [UserOrderController::class, 'list'])->name('order.list');
Route::match(['get', 'post'], '/order/register', [UserOrderController::class, 'register'])->name('order.register');
Route::post('/order/store', [UserOrderController::class, 'store'])->name('order.store');
Route::get('/order/history', [UserOrderController::class, 'history'])->name('order.history');
Route::get('/order/detail/{order}', [UserOrderController::class, 'detail'])->name('order.detail');

// API
Route::post('/api/order/history', [UserOrderController::class, 'getOrderHistory']);
Route::post('/api/merchant/member_list', [UserMerchantController::class, 'getMemberList']);
Route::post('/api/order/cancel', [UserOrderController::class, 'cancel'])->name('order.cancel');


Route::get('/register', [UserController::class, 'create'])->name('register.create');
Route::post('/register', [UserController::class, 'store'])->name('register.store');
// Route::resource('merchants', UserMerchantController::class)->except(['show']);
Route::get('merchants', [UserMerchantController::class, 'index'])->name('merchants.index');
Route::get('merchants/create', [UserMerchantController::class, 'create'])->name('merchants.create');
Route::post('merchants', [UserMerchantController::class, 'store'])->name('merchants.store');
Route::get('merchants/edit/{id}', [UserMerchantController::class, 'edit'])->name('merchants.edit'); // IDを渡す
Route::put('merchants/{id}', [UserMerchantController::class, 'update'])->name('merchants.update'); // IDを渡す
Route::delete('merchants/{merchant}', [UserMerchantController::class, 'destroy'])->name('merchants.destroy');
Route::get('/merchants/add_member', [UserMerchantController::class, 'add_member'])->name('merchants.add_member');
Route::post('/merchants/store_member', [UserMerchantController::class, 'storeMember'])->name('merchants.store_member');
Route::get('/merchants/information', [UserMerchantController::class, 'information'])->name('merchants.information');
Route::get('/merchants/member_list', [UserMerchantController::class, 'memberList'])->name('merchants.member_list');
Route::delete('/merchants/member/{id}', [UserMerchantController::class, 'destroy_member'])->name('merchant.member.destroy');

Route::post('/get-user-id', [UserController::class, 'getUserId']);
Route::post('/get-merchant-information', [UserMerchantController::class, 'getMerchantInformation']);



Route::get('/order/success', function () {
    return view('order.success');
})->name('order.success');

// Admin 用ルート
Route::prefix('admin')->name('admin.')->group(function () {
    // ダッシュボードのリダイレクト
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });
    // ログイン機能
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:admin')->group(function () {
        // ダッシュボード
        Route::get('index', [AdminDashboardController::class, 'index'])->name('dashboard');
        // 商品管理
        Route::resource('products', ProductController::class);
        Route::post('products/update-status', [ProductController::class, 'updateStatus'])->name('products.updateStatus');
        Route::resource('categories', CategoryController::class);
        Route::resource('agencies', AgencyController::class);
        Route::get('agencies/{agency}/edit-password', [AgencyController::class, 'editPassword'])->name('agencies.edit-password');
    Route::post('agencies/{agency}/update-password', [AgencyController::class, 'updatePassword'])->name('agencies.update-password');
        Route::post('agencies/update/{agency}', [AgencyController::class,'update'])->name('agencies.update');
        Route::resource('merchants', AdminMerchantController::class);
        Route::resource('orders', AdminOrderController::class)->only(['index', 'show', 'edit']);
        Route:: PUT('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::post('orders/bulk-update', [AdminOrderController::class, 'bulkUpdate'])->name('orders.bulk-update');
        Route::resource('users', AdminUserController::class);
        Route::post('/users/{user}/update-richmenu', [AdminUserController::class, 'updateRichmenu'])->name('admin.users.update-richmenu');
        // csvエクスポート
        Route::get('export/orders', [ExportController::class, 'exportOrders'])->name('export.orders');

        // ログ一覧
        Route::get('logs', [AdminLogController::class, 'index'])->name('logs.index');
        
        
    });
});

// Merchants 用ルート
Route::prefix('agencies')->name('agencies.')->group(function () {
    // ログイン機能
    Route::get('login', [AgencyAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AgencyAuthController::class, 'login']);
    Route::post('logout', [AgencyAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:agencies')->group(function () {
        Route::get('index', [AgencyDashboardController::class, 'index'])->name('dashboard');
        // 注文管理
        Route::get('orders/list', [AgencyOrderController::class,'list'])->name('orders.list');
        Route::post('orders/confirmation', [AgencyOrderController::class,'confirmation'])->name('orders.confirmation');
        Route::match(['get', 'post'],'orders/register', [AgencyOrderController::class,'register'])->name('orders.register');
        Route::post('orders/store', [AgencyOrderController::class,'store'])->name('orders.store');
        Route::get('orders/success', [AgencyOrderController::class,'success'])->name('orders.success');
        Route::post('orders/bulk-update', [AgencyOrderController::class, 'bulkUpdate'])->name('orders.bulk-update');
        Route::get('orders/complete', function () {
            return view('agencies.orders.complete');
        })->name('orders.complete');
        Route::resource('orders', AgencyOrderController::class);
        Route:: PUT('orders/{order}/status', [AgencyOrderController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::get('/merchants/invite', [AgencyMerchantController::class, 'invite'])->name('merchants.invite');
        Route::resource('merchants', AgencyMerchantController::class);
        Route::post('merchants/update/{merchant}', [AgencyMerchantController::class,'update'])->name('merchants.update');

        Route::resource('users', AgencynUserController::class);
        Route::post('/users/{user}/update-richmenu', [AgencynUserController::class, 'updateRichmenu'])->name('users.update-richmenu');
    });
});