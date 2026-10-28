<?php

use App\Http\Requests\StoreVehicleHandoverDraftMediaRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

it('validates draft video sizes and formats and permits removal', function (string $name, int $size, string $mime, bool $passes) {
    $file = UploadedFile::fake()->create($name, $size, $mime);
    $request = StoreVehicleHandoverDraftMediaRequest::create('/', 'POST', ['kind' => 'video', 'key' => 'general'], [], ['media' => $file]);
    expect(Validator::make($request->all(), $request->rules())->passes())->toBe($passes);
})->with([
    'longer video' => ['walkaround.mp4', 20 * 1024, 'video/mp4', true],
    'maximum video' => ['walkaround.mp4', 32 * 1024, 'video/mp4', true],
    'oversized video' => ['walkaround.mp4', 32 * 1024 + 1, 'video/mp4', false],
    'invalid format' => ['fake.txt', 10, 'text/plain', false],
]);

it('allows removing a video without a file', function () {
    $request = StoreVehicleHandoverDraftMediaRequest::create('/', 'POST', ['kind' => 'video', 'key' => 'general', 'media' => null]);
    expect(Validator::make($request->all(), $request->rules())->passes())->toBeTrue();
});

it('requires a photo and rejects oversized images', function () {
    $request = StoreVehicleHandoverDraftMediaRequest::create('/', 'POST', ['kind' => 'photo', 'key' => 'front_full']);
    expect(Validator::make($request->all(), $request->rules())->fails())->toBeTrue();
    $request->files->set('media', UploadedFile::fake()->image('front.jpg')->size(2049));
    expect(Validator::make($request->all(), $request->rules())->fails())->toBeTrue();
});
