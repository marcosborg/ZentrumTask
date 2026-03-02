<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\MediaProxyController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'index']);
Route::post('/contact', [WebsiteController::class, 'storeContact'])->name('contact.submit');
Route::get('/cms/{page}/{slug?}', [WebsiteController::class, 'showCms'])->name('cms.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{blogPost}/{slug?}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/candidatura', [CandidateApplicationController::class, 'show'])->name('candidatura.show');
Route::post('/candidatura/save', [CandidateApplicationController::class, 'save'])->name('candidatura.save');
Route::post('/candidatura/submit', [CandidateApplicationController::class, 'submit'])->name('candidatura.submit');
Route::post('/candidatura/upload', [CandidateApplicationController::class, 'upload'])->name('candidatura.upload');

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
        'matricula,km_semana',
        '"11-AA-22",2150',
        '"33-BB-44",1890',
    ]);

    return response()->streamDownload(function () use ($content): void {
        echo $content;
    }, 'exemplo-km-semanais.csv', [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
})->name('weekly-km.sample');
