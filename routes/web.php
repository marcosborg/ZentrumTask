<?php

use App\Http\Controllers\AppAuthController;
use App\Http\Controllers\AppCandidateApplicationController;
use App\Http\Controllers\AppContactController;
use App\Http\Controllers\AppDeviceTokenController;
use App\Http\Controllers\AppFrontpageDataController;
use App\Http\Controllers\AppKanbanController;
use App\Http\Controllers\AppOpsController;
use App\Http\Controllers\AppVehicleHandoverProcedureController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\MediaProxyController;
use App\Http\Controllers\WebsiteChatController;
use App\Http\Controllers\WebsiteController;
use App\Models\DriverSettlement;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', [WebsiteController::class, 'index']);
Route::options('/app/auth/login', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept',
]));
Route::post('/app/auth/login', [AppAuthController::class, 'login'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:20,1')
    ->name('app.auth.login');
Route::options('/app/auth/logout', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept',
]));
Route::post('/app/auth/logout', [AppAuthController::class, 'logout'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:20,1')
    ->name('app.auth.logout');
Route::options('/app/kanban/{path?}', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept',
]))->where('path', '.*');
Route::options('/app/ops/{path?}', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept',
]))->where('path', '.*');
Route::options('/app/devices/{path?}', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept',
]))->where('path', '.*');
Route::get('/app/kanban', [AppKanbanController::class, 'index'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.index');
Route::get('/app/kanban/search', [AppKanbanController::class, 'search'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.search');
Route::get('/app/ops/overview', [AppOpsController::class, 'overview'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.ops.overview');
Route::get('/app/ops/vehicle-handovers', [AppVehicleHandoverProcedureController::class, 'index'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.ops.vehicle-handovers.index');
Route::post('/app/ops/vehicle-handovers', [AppVehicleHandoverProcedureController::class, 'store'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:30,1')
    ->name('app.ops.vehicle-handovers.store');
Route::get('/app/ops/vehicle-handovers/{vehicleHandoverProcedure}', [AppVehicleHandoverProcedureController::class, 'show'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.ops.vehicle-handovers.show');
Route::post('/app/devices/register', [AppDeviceTokenController::class, 'store'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:120,1')
    ->name('app.devices.register');
Route::post('/app/devices/unregister', [AppDeviceTokenController::class, 'destroy'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:120,1')
    ->name('app.devices.unregister');
Route::get('/app/kanban/tasks/{task}', [AppKanbanController::class, 'show'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.tasks.show');
Route::post('/app/kanban/tasks/{task}/comments', [AppKanbanController::class, 'addComment'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.tasks.comments');
Route::post('/app/kanban/tasks/{task}/move', [AppKanbanController::class, 'move'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.tasks.move');
Route::delete('/app/kanban/tasks/{task}', [AppKanbanController::class, 'destroy'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.tasks.destroy');
Route::post('/app/kanban/tasks/{task}/restore', [AppKanbanController::class, 'restore'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.tasks.restore');
Route::post('/app/kanban/contacts', [AppKanbanController::class, 'storeContact'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:60,1')
    ->name('app.kanban.contacts.store');
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
Route::get('/frota', [WebsiteController::class, 'listVehicles'])->name('vehicle.index');
Route::get('/frota/{vehicle}/{slug?}', [WebsiteController::class, 'showVehicle'])->name('vehicle.show');
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
Route::options('/app/chat/{path}', fn () => response('', 204, [
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept',
]))->where('path', '.*');
Route::post('/app/chat/session', [WebsiteChatController::class, 'start'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:30,1')
    ->name('app.chat.session');
Route::post('/app/chat/message', [WebsiteChatController::class, 'message'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->middleware('throttle:30,1')
    ->name('app.chat.message');
Route::post('/contact', [WebsiteController::class, 'storeContact'])->name('contact.submit');
Route::get('/cms/{page}/{slug?}', [WebsiteController::class, 'showCms'])->name('cms.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{blogPost}/{slug?}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/reserva', [CandidateApplicationController::class, 'show'])->name('reserva.show');
Route::post('/reserva/save', [CandidateApplicationController::class, 'save'])->name('reserva.save');
Route::post('/reserva/submit', [CandidateApplicationController::class, 'submit'])->name('reserva.submit');
Route::post('/reserva/upload', [CandidateApplicationController::class, 'upload'])->name('reserva.upload');
Route::post('/reserva/payment', [CandidateApplicationController::class, 'payment'])->name('reserva.payment');
Route::get('/payments/ifthenpay/reserva/callback', [CandidateApplicationController::class, 'paymentCallback'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('payments.ifthenpay.reserva.callback');

Route::get('/candidatura', function () {
    $query = request()->query();

    return redirect()->route('reserva.show', $query);
})->name('candidatura.show');
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

Route::middleware(['auth'])->get('/admin/driver-settlements/{driverSettlement}/recibo-verde', function (DriverSettlement $driverSettlement) {
    $path = $driverSettlement->green_receipt_path;

    abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

    return Storage::disk('local')->download($path, basename($path));
})->name('driver-settlements.green-receipt.download');

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
