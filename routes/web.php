<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\AppContactController;
use App\Http\Controllers\AppCandidateApplicationController;
use App\Http\Controllers\AppFrontpageDataController;
use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\MediaProxyController;
use App\Http\Controllers\WebsiteChatController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'index']);
Route::options('/app/frontpage', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
]));
Route::get('/app/frontpage', AppFrontpageDataController::class)->name('app.frontpage');
Route::options('/app/contact', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept',
]));
Route::post('/app/contact', AppContactController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('app.contact.submit');
Route::options('/app/candidatura', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept',
]));
Route::options('/app/candidatura/{path}', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept',
]))->where('path', '.*');
Route::get('/app/candidatura', [AppCandidateApplicationController::class, 'show'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('app.candidatura.show');
Route::post('/app/candidatura/save', [AppCandidateApplicationController::class, 'save'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('app.candidatura.save');
Route::post('/app/candidatura/submit', [AppCandidateApplicationController::class, 'submit'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('app.candidatura.submit');
Route::post('/app/candidatura/upload', [AppCandidateApplicationController::class, 'upload'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('app.candidatura.upload');
Route::post('/contact', [WebsiteController::class, 'storeContact'])->name('contact.submit');
Route::get('/cms/{page}/{slug?}', [WebsiteController::class, 'showCms'])->name('cms.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{blogPost}/{slug?}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/candidatura', [CandidateApplicationController::class, 'show'])->name('candidatura.show');
Route::post('/candidatura/save', [CandidateApplicationController::class, 'save'])->name('candidatura.save');
Route::post('/candidatura/submit', [CandidateApplicationController::class, 'submit'])->name('candidatura.submit');
Route::post('/candidatura/upload', [CandidateApplicationController::class, 'upload'])->name('candidatura.upload');
Route::post('/chat/session', [WebsiteChatController::class, 'start'])
    ->middleware('throttle:30,1')
    ->name('website.chat.session');
Route::post('/chat/message', [WebsiteChatController::class, 'message'])
    ->middleware('throttle:30,1')
    ->name('website.chat.message');

Route::get('/media-proxy/{uuid}/{conversion?}', [MediaProxyController::class, 'show'])
    ->whereUuid('uuid')
    ->where('conversion', '[A-Za-z0-9_-]+')
    ->name('media.proxy');

Route::middleware(['auth'])->get('/admin/ajustes/exemplo.csv', function () {
    $content = implode("\n", [
        'motorista,data,valor,descricao,categoria,semanas',
        '"driver@example.com",2026-02-03,"50,00","Caucao semana 1",caucao,4',
        '"CODIGO123",2026-02-03,"15,00","Acerto lavagem",acerto,',
    ]);

    return response()->streamDownload(function () use ($content): void {
        echo $content;
    }, 'exemplo-ajustes.csv', [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
})->name('driver-adjustments.sample');

Route::middleware(['auth'])->get('/admin/km-semanais/exemplo.csv', function () {
    $content = implode("\n", [
        'matricula,km_total',
        '"11-AA-22",152340',
        '"33-BB-44",98320',
    ]);

    return response()->streamDownload(function () use ($content): void {
        echo $content;
    }, 'exemplo-km-semanais.csv', [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
})->name('weekly-km.sample');
