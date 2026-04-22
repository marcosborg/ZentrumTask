<?php

namespace App\Filament\Resources\CandidateApplications\Pages;

use App\Filament\Resources\CandidateApplications\CandidateApplicationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCandidateApplications extends ListRecords
{
    protected static string $resource = CandidateApplicationResource::class;

    protected ?string $heading = 'Reservas de viatura';

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Ativas')
                ->query(fn (Builder $query): Builder => $query->where(function (Builder $query): Builder {
                    return $query
                        ->whereNull('status')
                        ->orWhere('status', '!=', 'converted');
                })),
            'converted' => Tab::make('Convertidas')
                ->query(fn (Builder $query): Builder => $query->where('status', 'converted')),
        ];
    }
}
