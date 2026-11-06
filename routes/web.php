<?php

use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SecurityTxtController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman publik (URL lama dipertahankan)
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/profil', [PageController::class, 'profile'])->name('profile');
Route::get('/file', [PageController::class, 'rfc2350'])->name('rfc2350');
Route::get('/service', [PageController::class, 'services'])->name('services');
Route::get('/guidance', [PageController::class, 'guidances'])->name('guidances');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');

Route::get('/advisories', [AdvisoryController::class, 'index'])->name('advisories.index');
Route::get('/advisories/feed.xml', [AdvisoryController::class, 'feedAtom'])->name('advisories.feed.atom');
Route::get('/advisories/feed.json', [AdvisoryController::class, 'feedJson'])->name('advisories.feed.json');
Route::get('/advisories/{advisory}', [AdvisoryController::class, 'show'])->name('advisories.show');

// Aduan insiden siber
Route::get('/aduan', [TicketController::class, 'create'])->middleware('tickets.open')->name('tickets.create');
Route::post('/aduan', [TicketController::class, 'store'])->middleware(['tickets.open', 'throttle:tickets'])->name('tickets.store');
Route::get('/aduan/terkirim', [TicketController::class, 'submitted'])->name('tickets.submitted');
Route::get('/aduan/status', [TicketController::class, 'statusForm'])->name('tickets.status.form');
Route::post('/aduan/status', [TicketController::class, 'status'])->middleware('throttle:ticket-status')->name('tickets.status');
Route::post('/aduan/status/reply', [TicketController::class, 'reply'])->middleware('throttle:ticket-reply')->name('tickets.status.reply');

// RFC 9116
Route::get('/.well-known/security.txt', SecurityTxtController::class)->name('security-txt');
Route::redirect('/security.txt', '/.well-known/security.txt', 301);

Route::get('/captcha/reload', [LoginController::class, 'reloadCaptcha'])->middleware('throttle:30,1')->name('captcha.reload');

/*
|--------------------------------------------------------------------------
| Autentikasi (dibatasi whitelist IP)
|--------------------------------------------------------------------------
*/
Route::middleware(['login.ip', 'guest'])->group(function () {
    Route::get('/csirt-login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.create');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard')->name('dashboard.')->middleware(['auth', 'auth.session', 'active', 'session.absolute', '2fa'])->group(function () {
    Route::get('/', Dashboard\DashboardController::class)->name('index');

    // Keamanan akun (dapat diakses walau 2FA belum aktif)
    Route::controller(Dashboard\SecurityController::class)->prefix('security')->name('security.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/password', 'updatePassword')->name('password');
        Route::post('/two-factor', 'enableTwoFactor')->name('2fa.enable');
        Route::post('/two-factor/confirm', 'confirmTwoFactor')->middleware('throttle:10,1')->name('2fa.confirm');
        Route::post('/two-factor/recovery-codes', 'regenerateRecoveryCodes')->name('2fa.recovery');
        Route::delete('/two-factor', 'disableTwoFactor')->name('2fa.disable');
    });

    // Semua pengguna (penulis)
    Route::get('posts/checkSlug', [Dashboard\PostController::class, 'checkSlug'])->name('posts.checkSlug');
    Route::resource('posts', Dashboard\PostController::class);

    // Admin
    Route::middleware('can:admin')->group(function () {
        Route::get('categories/checkSlug', [Dashboard\CategoryController::class, 'checkSlug'])->name('categories.checkSlug');
        Route::resource('categories', Dashboard\CategoryController::class)->except('show');
        Route::resource('footers', Dashboard\FooterController::class)->except('show');
        Route::resource('properties', Dashboard\ImagePropertyController::class)->except('show');
        Route::resource('profils', Dashboard\ProfilController::class);
        Route::resource('services', Dashboard\ServiceController::class);
        Route::resource('files', Dashboard\FileController::class)->only(['index', 'create', 'store', 'destroy']);
        Route::resource('keys', Dashboard\KeyController::class)->only(['index', 'create', 'store', 'destroy']);
        Route::resource('guidances', Dashboard\GuidanceController::class)->except('show');

        Route::get('advisories/checkSlug', [Dashboard\AdvisoryController::class, 'checkSlug'])->name('advisories.checkSlug');
        Route::resource('advisories', Dashboard\AdvisoryController::class);

        Route::get('tickets', [Dashboard\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/export', [Dashboard\TicketController::class, 'export'])->name('tickets.export');
        Route::get('tickets/{ticket}', [Dashboard\TicketController::class, 'show'])->name('tickets.show');
        Route::put('tickets/{ticket}', [Dashboard\TicketController::class, 'update'])->name('tickets.update');
        Route::get('tickets/{ticket}/attachments/{attachment}', [Dashboard\TicketController::class, 'download'])->name('tickets.attachments.download');
    });

    // Superadmin
    Route::middleware('can:superadmin')->group(function () {
        Route::resource('users', Dashboard\UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::get('audit-logs', [Dashboard\AuditLogController::class, 'index'])->name('audit-logs.index');

        // Pengaturan Sistem: status, tugas terjadwal, dan pengaturan fitur.
        Route::get('system', [Dashboard\SystemController::class, 'index'])->name('system.index');
        Route::put('system/settings', [Dashboard\SystemController::class, 'updateSettings'])->name('system.settings');
        Route::post('system/tasks/{task}/run', [Dashboard\SystemController::class, 'runTask'])->middleware('throttle:6,1')->name('system.tasks.run');
    });
});

// URL lama
Route::redirect('/register', '/dashboard/users/create');
