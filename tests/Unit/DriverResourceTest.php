<?php

use App\Filament\Resources\Drivers\DriverResource;
use Filament\Forms\Components\FileUpload;

class TestDriverResource extends DriverResource
{
    public static function driverDocumentUploadForTest(string $name): FileUpload
    {
        return parent::driverDocumentUpload($name);
    }
}

it('stores driver documents without public S3 object ACLs', function () {
    $upload = TestDriverResource::driverDocumentUploadForTest('contract_file');

    expect($upload->getDiskName())->toBe('public')
        ->and($upload->getVisibility())->toBe('private')
        ->and($upload->shouldFetchFileInformation())->toBeFalse()
        ->and($upload->isPreviewable())->toBeFalse();
});
