<?php

use App\Http\Controllers\ActiveSessionController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryLocationController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\ECommerce\CartController as CartECommerceController;
use App\Http\Controllers\ECommerce\CheckoutController as CheckoutECommerceController;
use App\Http\Controllers\EnumController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentGatewayConfigController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\POS\CartController as CartPOSController;
use App\Http\Controllers\POS\CheckoutController as CheckoutPOSController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleInventoryController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Registration and Authentication Routes
|--------------------------------------------------------------------------
*/

Route::controller(LoginController::class)->group(function () {
    Route::post('login', 'login')->name('login');
    Route::post('logout', 'logout')->name('logout')->middleware('auth:sanctum');
});

Route::controller(\App\Http\Controllers\Auth\RegisterController::class)->group(function () {
    Route::post('register', 'register')->name('register');
});

Route::match(['PUT', 'PATCH'], 'change-password', \App\Http\Controllers\Auth\ChangePasswordController::class)
    ->middleware('auth:sanctum');

Route::controller(\App\Http\Controllers\Auth\ResetPasswordController::class)->group(function () {
    Route::post('forget-password', 'sendPasswordResetToken')->name('password-reset-token.send');
    Route::match(['PUT', 'PATCH'], 'verify-password-reset-token', 'verifyPasswordResetToken')->name('password-reset-token.verify');
    Route::match(['PUT', 'PATCH'], 'reset-password', 'resetPassword')->name('reset-password');
});

Route::get('enums', EnumController::class)->name('api.enums');

Route::prefix('sync')->name('sync')->group(function () {
    Route::get('products', [SyncController::class, 'products'])->name('sync.products');
    Route::get('products/{product}', [SyncController::class, 'showProduct'])->name('sync.products.show');
    Route::get('product-variants', [SyncController::class, 'productVariants'])->name('sync.productVariants');
    Route::get('product-variants/{productVariant}', [SyncController::class, 'showProductVariant'])->name('sync.productVariants.show');
});

Route::apiResource('stores', StoreController::class)
    ->only(['index', 'show']);

Route::prefix('products')->name('products.')->group(function () {
    Route::get('search', [ProductController::class, 'search'])->name('search');
});
Route::apiResource('products', ProductController::class)
    ->only(['index', 'show']);

Route::prefix('product-variants')->name('product-variants.')->group(function () {
    Route::get('search', [ProductVariantController::class, 'search'])->name('search');
});
Route::apiResource('product-variants', ProductVariantController::class)
    ->only(['index', 'show']);
Route::apiResource('categories', CategoryController::class)
    ->only(['index', 'show']);

Route::apiResource('payment-gateways', PaymentGatewayController::class)
    ->only(['index', 'show']);

Route::prefix('e-commerce/checkout')->name('e-commerce.checkout.')->group(function () {
    Route::controller(CheckoutECommerceController::class)->group(function () {
        Route::get('', 'summary')->name('checkout.index');
        Route::get('summary', 'summary')->name('checkout.summary');
        Route::match(['post', 'put', 'patch'], 'delivery-address', 'setDeliveryAddress')->name('checkout.devliveryAddress.upsert');
        Route::match(['post', 'put', 'patch'], 'payment-gateway', 'setPaymentGateway')->name('checkout.paymentGateway.upsert');
        Route::match(['post', 'put', 'patch'], 'coupon', 'applyCoupon')->name('checkout.coupon.add');
        Route::delete('coupon', 'removeCoupon')->name('checkout.coupon.remove');
        Route::match(['post', 'put', 'patch'], 'payment-gateway/{payment_gateway}', 'upsertPaymentGateway')->name('checkout.paymentGateway.upsert');
        Route::post('confirm-order', 'confirmOrder')->name('confirmOrder');
    });
});

Route::get('/callbacks/payment/{gateway}', [PaymentController::class, 'callback'])
    ->name('payment.callback');

Route::post('/webhooks/payment/{gateway}', [PaymentController::class, 'webhook'])
    ->name('payment.webhook');

Route::controller(CartECommerceController::class)->group(function () {
    Route::name('e-commerce.cart-items')->prefix('e-commerce/cart-items')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'add')->name('add');
        Route::match(['PUT', 'PATCH'], '/{id}', 'update')->name('update');
        Route::post('/{id}/increase-quantity', 'increase')->name('increase');
        Route::post('/{id}/decrease-quantity', 'decrease')->name('decrease');
        Route::delete('/{id}', 'remove')->name('remove');
        Route::delete('/', 'clear')->name('clear');
    });
});

/*
|--------------------------------------------------------------------------
| Auth Users' Routes : for only logged in users
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    Route::post('email/verification-token', [VerificationController::class, 'sendEmailVerificationToken'])
        ->withoutMiddleware('verified')
        ->name('email.verification-token');

    Route::post('email/verify', [VerificationController::class, 'verifyEmail'])
        ->withoutMiddleware('verified')
        ->name('email.verify');

    /**
     * User routes
     */
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('profile', [UserController::class, 'profile'])->name('profile.show');
        Route::post('profile', [UserController::class, 'updateProfile'])->name('profile.update');
        Route::apiResource('active-sessions', ActiveSessionController::class)->except(['store'])
            ->middleware('filter.merge.auth-user.tokenable');
    });

    Route::apiResource('attributes', AttributeController::class);
    Route::apiResource('attribute-values', AttributeValueController::class);
    Route::apiResource('currencies', CurrencyController::class);

    Route::resource('activities', ActivityController::class)
        ->only(['index']);


    Route::resource('orders', OrderController::class)
        ->only(['index', 'show','update']);

    Route::controller(PaymentController::class)->group(function () {
        Route::name('payments.')->prefix('payments/')->group(function () {
            Route::get('analytics', 'analytics')->name('analytics');
            Route::post('{payment}/verify', 'verify')->name('verify');
            Route::post('{payment}/reinitialize', 'reinitialize')->name('initialize');
        });
    });
    Route::resource('payments', PaymentController::class)
        ->only(['index', 'show']);

    Route::apiResource('payment-gateways', PaymentGatewayController::class)
        ->only(['update']);
    Route::apiResource('payment-gateway-configs', PaymentGatewayConfigController::class);
    Route::apiResource('delivery-locations', DeliveryLocationController::class);

    Route::prefix('products/{product}/')->name('product-variants')->group(function () {
        Route::controller(ProductController::class)->group(function () {
            Route::prefix('images')->name('images.')->group(function () {
                Route::post('/', 'uploadImage')->name('upload');
                Route::match(['PUT', 'PATCH'], '/{image}', 'updateImage')->name('update');
                Route::delete('/{image}', 'deleteImage')->name('destroy');
            });

            Route::prefix('categories')->name('categories.')->group(function () {
                Route::match(['PUT', 'PATCH'], '/', 'syncCategories')->name('sync');
                Route::post('{category}', 'addCategory')->name('add');
                Route::delete('{category}', 'removeCategory')->name('remove');
            });

            Route::prefix('attribute-values')->name('attribute-values.')->group(function () {
                Route::match(['PUT', 'PATCH'], '/', 'syncAttributeValues')->name('sync');
                Route::post('/{attribute_value}', 'addAttributeValue')->name('add');
                Route::delete('/{attribute_value}', 'removeAttributeValue')->name('remove');
            });
        });
    });
    Route::apiResource('products', ProductController::class)
        ->only(['store', 'update', 'destroy']);

    Route::prefix('product-variants/{product_variant}/')->name('product-variants')->group(function () {
        Route::controller(ProductVariantController::class)->group(function () {
            Route::prefix('images')->name('images.')->group(function () {
                Route::post('/', 'uploadImage')->name('upload');
                Route::match(['PUT', 'PATCH'], '/{image}', 'updateImage')->name('update');
                Route::delete('/{image}', 'deleteImage')->name('destroy');
            });
            Route::prefix('attribute-values')->name('attribute-values.')->group(function () {
                Route::match(['PUT', 'PATCH'], '/', 'syncAttributeValues')->name('sync');
                Route::post('/{attribute_value}', 'addAttributeValue')->name('add');
                Route::delete('/{attribute_value}', 'removeAttributeValue')->name('remove');
            });
        });
    });
    Route::apiResource('product-variants', ProductVariantController::class)
        ->only(['store', 'update', 'destroy']);

    Route::apiResource('inventories', InventoryController::class);
    Route::apiResource('coupons', CouponController::class);

    Route::apiResource('stores', StoreController::class)
        ->only(['store', 'update', 'destroy']);

    Route::apiResource('staff', StaffController::class);
    Route::prefix('roles/{role}')->name('roles.')->group(function () {
        Route::post('sync-permissions', [RoleController::class, 'syncPermissions'])
            ->name('sync-permissions');
    });
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class)
        ->only(['index', 'show']);

    Route::apiResource('discounts', DiscountController::class);

    Route::controller(EnumController::class)->group(function () {
        Route::delete('enums/cache', 'clearCache')->name('enums.cache.clear');
        Route::get('enums/cache/stats', 'getCacheStats')->name('enums.cache.stats');
    });

    Route::prefix('users/{user}')->name('users.')->group(function () {
        Route::controller(UserController::class)->group(function () {
            Route::post('deactivate', 'deactivate')->name('deactivate');
            Route::post('reactivate', 'reactivate')->name('reactivate');
            Route::post('notifications/send-mail', 'sendMail')->name('notifications.send-mail');
            Route::post('sync-roles', 'syncRoles')->name('sync-roles');
            Route::post('sync-permissions', 'syncPermissions')->name('sync-permissions');
        });
    });
    Route::apiResource('users', UserController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('categories', CategoryController::class)
        ->only(['store', 'update', 'destroy']);

    /**
     * Admin routes
     */
    Route::prefix('admin')->name('admin.')->middleware(['role:admin'])->group(function () {});

    Route::match(['PUT', 'PATCH'], 'notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
    Route::match(['PUT', 'PATCH'], 'notifications/mark-all-as-unread', [NotificationController::class, 'markAllAsUnread'])->name('notifications.mark-all-as-unread');
    Route::apiResource('notifications', NotificationController::class)->except(['store']);
    Route::match(['PUT', 'PATCH'], 'notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::match(['PUT', 'PATCH'], 'notifications/{notification}/mark-as-unread', [NotificationController::class, 'markAsUnread'])->name('notifications.mark-as-unread');

    Route::apiResource('sales', SaleController::class);
    Route::prefix('sales/{sale}')->name('sales.')->group(function () {
        Route::post('sale-inventories', [SaleController::class, 'storeSaleInventory'])
            ->name('sale-inventories.store');
        Route::delete('sale-inventories/{sale_inventory}', [SaleController::class, 'destroySaleInventory'])
            ->name('sale-inventories.destroy');
    });
    Route::apiResource('sale-inventories', SaleInventoryController::class);
    Route::get('inventory-products', [InventoryController::class, 'index']);
    Route::get('inventory-products/{item}', [InventoryController::class, 'show']);
    Route::prefix('inventory')->name('inventory')->group(function () {
        Route::apiResource('products', InventoryController::class);
        Route::prefix('scrapes/{scrape}')->name('scrapes.')->group(function () {
            Route::controller(ScrapeController::class)->group(function () {
                Route::post('add-to-inventory', 'addToInventory')->name('add-to-inventory');
            });
        });
        Route::apiResource('scrapes', ScrapeController::class);
    });

    Route::prefix('stock-transfers/{stock_transfer}')->name('stock-transfer.')->group(function () {
        Route::controller(StockTransferController::class)->group(function () {
            Route::post('dispatch', 'dispatch')->name('dispatch');
            Route::post('accept', 'accept')->name('accept');
            Route::post('reject', 'reject')->name('reject');
            Route::delete('stock-transfer-inventories/{stock_transfer_inventory}', 'destroyStockTransferInventory')
                ->name('stock-transfer-inventories.destroy');
        });
    });
    Route::apiResource('stock-transfers', StockTransferController::class);

    Route::controller(CartPOSController::class)->group(function () {
        Route::name('pos.cart-items')->prefix('pos/cart-items')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'add')->name('add');
            Route::match(['PUT', 'PATCH'], '/{id}', 'update')->name('update');
            Route::post('/{id}/increase-quantity', 'increase')->name('increase');
            Route::post('/{id}/decrease-quantity', 'decrease')->name('decrease');
            Route::delete('/{id}', 'remove')->name('remove');
            Route::delete('/', 'clear')->name('clear');
        });
    });

    Route::post('pos/checkout', CheckoutPOSController::class)
        ->name('pos.checkout');

});

/*
|--------------------------------------------------------------------------
| Customer Support
|--------------------------------------------------------------------------
*/

// Route::post('contact-us', [SupportController::class, 'contactUs'])->name('support.contact.us');

/*
|--------------------------------------------------------------------------
| Other Routes
|--------------------------------------------------------------------------
*/
