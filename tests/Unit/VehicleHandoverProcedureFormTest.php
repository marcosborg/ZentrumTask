<?php

use App\Filament\Resources\VehicleHandoverProcedures\Schemas\VehicleHandoverProcedureForm;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Filesystem\FilesystemAdapter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TestVehicleHandoverProcedureForm extends VehicleHandoverProcedureForm
{
    public static function handoverUploadForTest(string $name): FileUpload
    {
        return parent::handoverUpload($name);
    }

    public static function storeHandoverUploadForTest(BaseFileUpload $component, TemporaryUploadedFile $file): ?string
    {
        return parent::storeHandoverUpload($component, $file);
    }
}

it('does not require storage metadata to preview existing handover media', function () {
    $upload = TestVehicleHandoverProcedureForm::handoverUploadForTest('photo');

    expect($upload->shouldFetchFileInformation())->toBeFalse();
});

it('stores backoffice handover uploads without public object ACLs', function () {
    $component = Mockery::mock(BaseFileUpload::class);
    $file = Mockery::mock(TemporaryUploadedFile::class);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'image contents');
    rewind($stream);

    $file->shouldReceive('exists')->once()->andReturnTrue();
    $component->shouldReceive('shouldMoveFiles')->once()->andReturnFalse();
    $component->shouldReceive('getDirectory')->once()->andReturn('vehicle-handovers/guided-photos');
    $component->shouldReceive('getUploadedFileNameForStorage')->once()->with($file)->andReturn('photo.jpeg');
    $component->shouldReceive('getDisk')->once()->andReturn($disk);
    $file->shouldReceive('readStream')->once()->andReturn($stream);
    $file->shouldNotReceive('storePubliclyAs');
    $file->shouldNotReceive('storeAs');
    $disk->shouldReceive('put')
        ->once()
        ->with('vehicle-handovers/guided-photos/photo.jpeg', $stream)
        ->andReturnTrue();

    expect(TestVehicleHandoverProcedureForm::storeHandoverUploadForTest($component, $file))
        ->toBe('vehicle-handovers/guided-photos/photo.jpeg');
});
