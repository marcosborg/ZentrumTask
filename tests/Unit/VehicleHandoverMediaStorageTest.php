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
