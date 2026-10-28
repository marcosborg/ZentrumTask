<?php

use App\Services\VehicleHandoverProcedureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

class TestVehicleHandoverProcedureService extends VehicleHandoverProcedureService
{
    public function storePhotoForTest(mixed $photo): ?string
    {
        return $this->storeSinglePhoto($photo, 'vehicle-handovers/general-photos');
    }

    public function storeVideoForTest(mixed $video): ?string
    {
        return $this->storeSingleVideo($video);
    }
}

it('ignores invalid handover photo payloads instead of failing the save', function () {
    Storage::fake('public');

    $service = new TestVehicleHandoverProcedureService;

    expect($service->storePhotoForTest('data:image/png;base64,not-valid-base64'))->toBeNull()
        ->and($service->storePhotoForTest('data:image/png;base64'))->toBeNull();
});

it('ignores handover videos that cannot be stored instead of failing the save', function () {
    Storage::fake('public');

    $service = new TestVehicleHandoverProcedureService;
    $temporaryPath = tempnam(sys_get_temp_dir(), 'handover-video-');

    expect($temporaryPath)->toBeString();

    $missingVideo = new UploadedFile(
        $temporaryPath,
        'handover-video.mp4',
        'video/mp4',
        null,
        true,
    );

    unlink($temporaryPath);

    expect($service->storeVideoForTest($missingVideo))->toBeNull();
});

it('stores multipart photos without converting them to base64', function () {
    Storage::fake('public');
    $path = (new TestVehicleHandoverProcedureService)->storePhotoForTest(UploadedFile::fake()->image('front.jpg'));
    expect($path)->toBeString();
    Storage::disk('public')->assertExists($path);
});

it('does not confirm a draft photo when storage failed', function () {
    $draft = new \App\Models\VehicleHandoverProcedure(['status' => 'draft']);
    (new TestVehicleHandoverProcedureService)->storeDraftMedia($draft, 'photo', 'wheel_front_left', 'data:image/png;base64,invalid-base64');
})->throws(\Illuminate\Validation\ValidationException::class);
