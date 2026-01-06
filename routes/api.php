<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\NavigationController;
use App\Http\Controllers\Api\CertificateApiController;
use App\Http\Controllers\Api\RootCaApiController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\PublicCaController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\MailController;
use App\Http\Controllers\Api\DashboardController;

// Fallback login route to prevent 500 error on auth failure
Route::get('/auth/login-fallback', function () {
    return response()->json(['error' => 'Unauthenticated'], 401);
})->name('login');

// Public API Routes
Route::get('/public/ca-certificates', [PublicCaController::class, 'index']);
Route::get('/public/ca-certificates/{serial}/download', [PublicCaController::class, 'download']);
Route::get('/public/ca-certificates/{serial}/download/windows', [PublicCaController::class, 'downloadWindows']);
Route::get('/public/ca-certificates/{serial}/download/mac', [PublicCaController::class, 'downloadMac']);
Route::get('/public/ca-certificates/{serial}/download/linux', [PublicCaController::class, 'downloadLinux']);
Route::post('/public/inquiries', [\App\Http\Controllers\Api\InquiryController::class, 'store']);
Route::get('/public/legal-pages', [\App\Http\Controllers\Api\LegalPageController::class, 'index']);
Route::get('/public/legal-pages/{slug}', [\App\Http\Controllers\Api\LegalPageController::class, 'show']);

// Auth routes moved to web.php for SPA session support

// Auth routes moved to web.php for SPA session support (manually prefixed with /api there)
// This ensures they use the 'web' middleware stack for proper session persistence.
Route::get('/navigation-debug', [NavigationController::class, 'debug']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::delete('/auth/social/{provider}', [AuthController::class, 'disconnectSocial']);
    Route::post('/auth/set-password', [AuthController::class, 'setPassword']);
    Route::get('/auth/link-token', [AuthController::class, 'getLinkToken']);

    Route::get('/user', function (Request $request) {
        return $request->user()->load('socialAccounts');
    });
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/navigation', [NavigationController::class, 'index']);

    // Core Features (Require Email Verification)
    Route::middleware(['verified'])->group(function () {
        // Certificate Routes
        Route::get('/certificates', [CertificateApiController::class, 'index']);
        Route::post('/certificates', [CertificateApiController::class, 'store']);
        Route::get('/certificates/{certificate}', [CertificateApiController::class, 'show']);
        Route::delete('/certificates/{certificate}', [CertificateApiController::class, 'destroy']);
        Route::get('/certificates/{certificate}/download/{type}', [CertificateApiController::class, 'downloadFile']);
        
        // CA Management (Admin)
        Route::post('/ca/setup', [CertificateApiController::class, 'setupCa']);

        // Root CA Management (Admin Only)
        Route::get('/admin/ca-certificates', [RootCaApiController::class, 'index']);
        Route::post('/admin/ca-certificates/sync-cdn', [RootCaApiController::class, 'syncToCdn']);
        Route::post('/admin/ca-certificates/sync-crt', [RootCaApiController::class, 'syncCrtOnly']);
        Route::post('/admin/ca-certificates/sync-installers', [RootCaApiController::class, 'syncInstallersOnly']);
        Route::post('/admin/ca-certificates/sync-bundles', [RootCaApiController::class, 'syncBundlesOnly']);
        Route::post('/admin/ca-certificates/{certificate}/renew', [RootCaApiController::class, 'renew']);

        // API Keys Management
        Route::get('/api-keys', [ApiKeyController::class, 'index']);
        Route::post('/api-keys', [ApiKeyController::class, 'store']);
        Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy']);
        Route::patch('/api-keys/{id}/toggle', [ApiKeyController::class, 'toggle']);
        Route::post('/api-keys/{id}/regenerate', [ApiKeyController::class, 'regenerate']);

        // Profile Management (Sensitive parts)
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
        Route::get('/profile/login-history', [ProfileController::class, 'getLoginHistory']);
        Route::delete('/profile', [ProfileController::class, 'deleteAccount']);
        Route::get('/profile/sessions', [ProfileController::class, 'getActiveSessions']);
        Route::delete('/profile/sessions/{id}', [ProfileController::class, 'revokeSession']);

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/ping', [DashboardController::class, 'ping']);

        // Support Tickets
        Route::get('/support/tickets', [\App\Http\Controllers\Api\TicketController::class, 'index']);
        Route::post('/support/tickets', [\App\Http\Controllers\Api\TicketController::class, 'store']);
        Route::get('/support/tickets/{id}', [\App\Http\Controllers\Api\TicketController::class, 'show']);
        Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\Api\TicketController::class, 'reply']);
        Route::patch('/support/tickets/{id}/close', [\App\Http\Controllers\Api\TicketController::class, 'close']);
        Route::get('/support/attachments/{attachment}', [\App\Http\Controllers\Api\AttachmentController::class, 'download']);

        // User Management (Admin Only)
        Route::apiResource('/admin/users', UserApiController::class);

        // Inquiry Management (Admin Only)
        Route::middleware(['admin'])->group(function () {
            Route::get('/admin/inquiries', [\App\Http\Controllers\Api\InquiryController::class, 'index']);
            Route::get('/admin/inquiries/{inquiry}', [\App\Http\Controllers\Api\InquiryController::class, 'show']);
            Route::post('/admin/inquiries/{inquiry}/reply', [\App\Http\Controllers\Api\InquiryController::class, 'reply']);
            Route::delete('/admin/inquiries/{inquiry}', [\App\Http\Controllers\Api\InquiryController::class, 'destroy']);
        });

        // SMTP Testing (Admin Only)
        Route::middleware(['admin'])->group(function () {
            Route::get('/admin/smtp/config', [MailController::class, 'getConfigurations']);
            Route::post('/admin/smtp/test', [MailController::class, 'sendTestEmail']);
        });

        // Legal Pages Management (Admin Only)
        Route::middleware(['admin'])->group(function () {
            Route::get('/admin/legal-pages', [\App\Http\Controllers\Api\Admin\LegalPageController::class, 'index']);
            Route::post('/admin/legal-pages', [\App\Http\Controllers\Api\Admin\LegalPageController::class, 'store']);
            Route::get('/admin/legal-pages/{legalPage}', [\App\Http\Controllers\Api\Admin\LegalPageController::class, 'show']);
            Route::get('/admin/legal-pages/{id}/history', [\App\Http\Controllers\Api\Admin\LegalPageController::class, 'getHistory']);
            Route::put('/admin/legal-pages/{legalPage}', [\App\Http\Controllers\Api\Admin\LegalPageController::class, 'update']);
            Route::delete('/admin/legal-pages/{legalPage}', [\App\Http\Controllers\Api\Admin\LegalPageController::class, 'destroy']);
        });
    });


});

// Authenticated API Routes (v1) - Using API Key
Route::middleware([\App\Http\Middleware\CheckApiKey::class])->prefix('v1')->group(function () {
    Route::get('/certificates', [CertificateApiController::class, 'index']);
});
