<?php

namespace App\Filament\Resources\VanReservations\Schemas;

use App\Models\VanReservation;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VanReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        $fields = [];
        foreach (['reference' => 'Referência', 'name' => 'Cliente', 'email' => 'Email', 'phone' => 'Telefone', 'origin' => 'Origem', 'destination' => 'Destino', 'driver_name' => 'Motorista'] as $key => $label) {
            $fields[] = TextInput::make($key)->label($label)->disabled()->dehydrated(false);
        }

        return $schema->components([
            Section::make('Pedido de aluguer')->columns(2)->columnSpanFull()->schema([
                ...$fields,
                TextInput::make('van_name')->label('Carrinha')->formatStateUsing(fn ($record) => $record?->van?->name)->disabled()->dehydrated(false),
                TextInput::make('status')->label('Estado')->formatStateUsing(fn ($state) => VanReservation::STATUSES[$state] ?? $state)->disabled()->dehydrated(false),
                TextInput::make('mode')->label('Modalidade')->formatStateUsing(fn ($state) => $state === 'with_driver' ? 'Com motorista' : 'Sem motorista')->disabled()->dehydrated(false),
                TextInput::make('purpose')->label('Finalidade')->formatStateUsing(fn ($state) => $state === 'moving' ? 'Mudanças' : 'Mercadorias')->disabled()->dehydrated(false),
                TextInput::make('loading_help')->label('Ajuda de carga/descarga sob orçamento')->formatStateUsing(fn ($state) => $state ? 'Sim' : 'Não')->disabled()->dehydrated(false),
                TextInput::make('period')->label('Período — hora local')->formatStateUsing(fn ($record) => $record?->starts_at->timezone('Europe/Lisbon')->format('d/m/Y H:i').' → '.$record?->ends_at->timezone('Europe/Lisbon')->format('d/m/Y H:i'))->disabled()->dehydrated(false)->columnSpanFull(),
                TextInput::make('estimate')->label('Estimativa guardada (IVA incluído)')->formatStateUsing(fn ($record) => $record ? $record->billable_hours.' h × '.number_format($record->hourly_rate / 100, 2, ',', ' ').' € = '.number_format($record->estimated_total / 100, 2, ',', ' ').' €; caução: '.number_format($record->deposit / 100, 2, ',', ' ').' €' : '')->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('notes')->label('Observações do cliente')->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('saved_terms')->label('Condições guardadas no pedido')->formatStateUsing(fn ($record) => collect($record?->terms)->map(fn ($value, $key) => $key.': '.$value)->implode("\n"))->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('kanban_error')->label('Integração Kanban')->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('internal_notes')->label('Notas internas')->maxLength(10000)->columnSpanFull(),
                Textarea::make('history')->label('Histórico')->formatStateUsing(fn ($record) => $record?->events()->with('user')->get()->map(fn ($event) => $event->created_at->timezone('Europe/Lisbon')->format('d/m/Y H:i').' · '.($event->user?->name ?? 'Website').' · '.(VanReservation::STATUSES[$event->to_status] ?? $event->to_status).' · '.$event->reason.($event->details ? "\n".json_encode($event->details, JSON_UNESCAPED_UNICODE) : ''))->implode("\n\n"))->rows(8)->disabled()->dehydrated(false)->columnSpanFull(),
            ]),
        ]);
    }
}
