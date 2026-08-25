<?php

use App\Filament\Resources\Drivers\DriverResource;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Filesystem\FilesystemAdapter;

class TestDriverResource extends DriverResource
{
    public static function driverDocumentUploadForTest(string $name): FileUpload
    {
        return parent::driverDocumentUpload($name);
    }

    /**
     * @return array{name: string, size: int, type: null, url: string}
     */
    public static function uploadedDriverDocumentForTest(BaseFileUpload $component, string $file, string|array|null $storedFileNames): array
    {
        return parent::uploadedDriverDocument($component, $file, $storedFileNames);
    }
}

it('stores driver documents without public S3 object ACLs', function () {
    $upload = TestDriverResource::driverDocumentUploadForTest('contract_file');

    expect($upload->getDiskName())->toBe('public')
        ->and($upload->getVisibility())->toBe('private')
        ->and($upload->shouldFetchFileInformation())->toBeFalse()
        ->and($upload->isPreviewable())->toBeFalse();
});

it('loads existing driver documents through the configured media url', function () {
    $component = Mockery::mock(BaseFileUpload::class);
    $disk = Mockery::mock(FilesystemAdapter::class);

    $component->shouldReceive('isMultiple')->once()->andReturnFalse();
    $component->shouldReceive('getDisk')->once()->andReturn($disk);
    $disk->shouldReceive('url')
        ->once()
        ->with('drivers/30/contract/contract.pdf')
        ->andReturn('https://media.example.com/drivers/30/contract/contract.pdf');

    expect(TestDriverResource::uploadedDriverDocumentForTest(
        $component,
        'drivers/30/contract/contract.pdf',
        null,
    ))->toBe([
        'name' => 'contract.pdf',
        'size' => 0,
        'type' => null,
        'url' => 'https://media.example.com/drivers/30/contract/contract.pdf',
    ]);
});
