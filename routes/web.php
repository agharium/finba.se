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