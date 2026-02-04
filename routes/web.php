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
