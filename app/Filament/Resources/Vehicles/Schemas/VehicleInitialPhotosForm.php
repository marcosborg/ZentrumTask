<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VehicleInitialPhotosForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fotografias iniciais')
                    ->description('Gerir as fotografias iniciais associadas a esta viatura.')
                    ->columnSpanFull()
                    ->columns(1)
                    ->components([
                        SpatieMediaLibraryFileUpload::make('vehicle_photos')
                            ->label('Fotos')
                            ->collection('vehicle_photos')
                            ->multiple()
                            ->columnSpanFull()
                            ->panelLayout('grid')
                            ->itemPanelAspectRatio(1)
                            ->imagePreviewHeight('96')
                            ->imageResizeMode('cover')
                            ->extraAttributes([
                                'class' => 'vehicle-photo-upload',
                            ])
                            ->downloadable()
                            ->openable()
                            ->reorderable()
                            ->preserveFilenames()
                            ->getUploadedFileUsing(static function (SpatieMediaLibraryFileUpload $component, string $file): ?array {
                                $media = Media::query()->where('uuid', $file)->first();

                                if (! $media) {
                                    return null;
                                }

                                $conversion = $component->getConversion();

                                return [
                                    'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                                    'size' => $media->getAttributeValue('size'),
                                    'type' => $media->getAttributeValue('mime_type'),
                                    'url' => route('media.proxy', [
                                        'uuid' => $media->getAttributeValue('uuid'),
                                        'conversion' => ($conversion && $media->hasGeneratedConversion($conversion)) ? $conversion : null,
                                    ]),
                                ];
                            }),
                    ]),
            ]);
    }
}
