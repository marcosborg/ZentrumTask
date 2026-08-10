<?php

use App\Filament\Resources\VehicleHandoverProcedures\Schemas\VehicleHandoverProcedureForm;
use Filament\Forms\Components\FileUpload;

class TestVehicleHandoverProcedureForm extends VehicleHandoverProcedureForm
{
    public static function handoverUploadForTest(string $name): FileUpload
    {
        return parent::handoverUpload($name);
    }
}

it('does not require storage metadata to preview existing handover media', function () {
    $upload = TestVehicleHandoverProcedureForm::handoverUploadForTest('photo');

    expect($upload->shouldFetchFileInformation())->toBeFalse();
});
