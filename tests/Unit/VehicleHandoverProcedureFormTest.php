<?php

use App\Filament\Resources\VehicleHandoverProcedures\Schemas\VehicleHandoverProcedureForm;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
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

    $file->shouldReceive('exists')->once()->andReturnTrue();
    $component->shouldReceive('shouldMoveFiles')->once()->andReturnFalse();
    $component->shouldReceive('getDirectory')->once()->andReturn('vehicle-handovers/guided-photos');
    $component->shouldReceive('getUploadedFileNameForStorage')->once()->with($file)->andReturn('photo.jpeg');
    $component->shouldReceive('getDiskName')->once()->andReturn('public');
    $file->shouldReceive('storeAs')
        ->once()
        ->with('vehicle-handovers/guided-photos', 'photo.jpeg', 'public')
        ->andReturn('vehicle-handovers/guided-photos/photo.jpeg');
    $file->shouldNotReceive('storePubliclyAs');

    expect(TestVehicleHandoverProcedureForm::storeHandoverUploadForTest($component, $file))
        ->toBe('vehicle-handovers/guided-photos/photo.jpeg');
});
