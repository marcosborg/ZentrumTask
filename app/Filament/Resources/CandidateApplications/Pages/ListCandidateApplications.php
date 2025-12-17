<?php

namespace App\Filament\Resources\CandidateApplications\Pages;

use App\Filament\Resources\CandidateApplications\CandidateApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListCandidateApplications extends ListRecords
{
    protected static string $resource = CandidateApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
