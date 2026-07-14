<?php


use App\Http\Controllers\SocialiteController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Verified;

// Route::get('/', function () {
//     return view('welcome');
// });

// Route::get('/email/verify/{id}/{hash}', function (Request $request, string $id, string $hash) {
//     $user = User::findOrFail($id);

//     if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
//         abort(403);
//     }

//     if (! $user->hasVerifiedEmail()) {
//         $user->markEmailAsVerified();

//         event(new Verified($user));
//     }

//     return redirect('/');
// })->middleware(['signed'])->name('verification.verify');

// Route::post('/email/verification-notification', function (Request $request) {
//     $request->user()->sendEmailVerificationNotification();

//     return back();
// })->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->name('socialite.redirect');

Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])
    ->name('socialite.callback');

Route::get('/manifest.webmanifest', function () {
    return response(file_get_contents(public_path('manifest.webmanifest')), 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'no-cache',
    ]);
})->name('pwa.manifest');

Route::get('/service-worker.js', function () {
    return response(file_get_contents(public_path('service-worker.js')), 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'no-cache',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('pwa.service-worker');

Route::get('/offline.html', function () {
    return response(file_get_contents(public_path('offline.html')), 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-cache',
    ]);
})->name('pwa.offline');
