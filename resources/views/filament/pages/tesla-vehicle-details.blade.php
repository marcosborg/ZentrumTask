<x-filament-panels::page>
    <style>
        .tesla-detail {
            display: grid;
            gap: 1.25rem;
        }

        .tesla-detail__top {
            align-items: center;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }

        .tesla-detail__meta {
            color: rgb(156 163 175);
            font-size: .9rem;
        }

        .tesla-detail__grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .tesla-detail__card {
            background: color-mix(in srgb, currentColor 4%, transparent);
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .875rem;
            padding: 1rem;
        }

        .tesla-detail__label {
            color: rgb(156 163 175);
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tesla-detail__value {
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.1;
            margin-top: .55rem;
        }

        .tesla-detail__split {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr 1fr;
        }

        .tesla-detail__chart {
            align-items: end;
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            display: flex;
            gap: .35rem;
            height: 11rem;
            padding-top: 1rem;
        }

        .tesla-detail__bar {
            background: linear-gradient(180deg, rgb(34 197 94), rgb(22 163 74));
            border-radius: .35rem .35rem 0 0;
            flex: 1;
            min-width: .55rem;
            position: relative;
        }

        .tesla-detail__bar--charge {
            background: linear-gradient(180deg, rgb(251 146 60), rgb(234 88 12));
        }

        .tesla-detail__bar span {
            color: rgb(209 213 219);
            font-size: .7rem;
            left: 50%;
            position: absolute;
            top: -.95rem;
            transform: translateX(-50%);
        }

        .tesla-detail__empty {
            color: rgb(156 163 175);
            padding: 2rem 0;
            text-align: center;
        }

        .tesla-detail__list {
            display: grid;
            gap: .65rem;
        }

        .tesla-detail__row {
            align-items: center;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
            display: grid;
            gap: .75rem;
            grid-template-columns: 1fr auto;
            padding: .6rem 0;
        }

        .tesla-detail__raw {
            background: rgba(2, 6, 23, .35);
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: .75rem;
            font-size: .78rem;
            max-height: 28rem;
            overflow: auto;
            padding: 1rem;
            white-space: pre-wrap;
        }

        .tesla-detail__map {
            border: 0;
            border-radius: .875rem;
            height: 18rem;
            overflow: hidden;
            width: 100%;
        }

        @media (max-width: 1100px) {
            .tesla-detail__grid,
            .tesla-detail__split {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $snapshot = $this->latestSnapshot();
        $chargingEvents = $this->recentChargingEvents();
        $latestCharge = $chargingEvents->first();
        $errors = $this->recentErrors();
        $rawPayload = $this->latestRawPayload();
        $manualSnapshots = $this->manualOdometerSnapshots();
        $hasLocation = $snapshot?->latitude && $snapshot?->longitude;
        $mapQuery = $hasLocation ? urlencode($snapshot->latitude . ',' . $snapshot->longitude) : null;
    @endphp

    <div class="tesla-detail">
        <div class="tesla-detail__top">
            <div class="tesla-detail__meta">
                VIN {{ $vehicle->vin }} · Ultima atualizacao {{ $vehicle->last_seen_at?->format('Y-m-d H:i') ?: '-' }}
            </div>

            <x-filament::button tag="a" href="{{ \App\Filament\Pages\TeslaIntegration::getUrl() }}" color="gray" icon="heroicon-m-arrow-left">
                Voltar
            </x-filament::button>
        </div>

        <x-filament::section>
            <div class="tesla-detail__grid">
                @foreach ($this->summaryCards() as $card)
                    <div class="tesla-detail__card">
                        <div class="tesla-detail__label">{{ $card['label'] }}</div>
                        <div class="tesla-detail__value">{{ $card['value'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <div class="tesla-detail__split">
            <x-filament::section heading="Bateria">
                @if ($this->batteryBars() === [])
                    <div class="tesla-detail__empty">Ainda nao existem snapshots suficientes.</div>
                @else
                    <div class="tesla-detail__chart">
                        @foreach ($this->batteryBars() as $bar)
                            <div class="tesla-detail__bar" style="height: {{ max(6, $bar['value']) }}%;" title="{{ $bar['label'] }} · {{ $bar['value'] }}%">
                                <span>{{ $bar['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Carregamentos">
                @if ($this->chargingBars() === [])
                    <div class="tesla-detail__empty">Sem historico de carregamentos disponivel.</div>
                @else
                    <div class="tesla-detail__chart">
                        @foreach ($this->chargingBars() as $bar)
                            <div class="tesla-detail__bar tesla-detail__bar--charge" style="height: {{ $bar['height'] }}%;" title="{{ $bar['label'] }} · {{ number_format($bar['value'], 1, ',', ' ') }} kWh">
                                <span>{{ number_format($bar['value'], 0, ',', ' ') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        <div class="tesla-detail__split">
            <x-filament::section heading="Carga e clima">
                <div class="tesla-detail__list">
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Estado de carregamento</span>
                        <strong>{{ $snapshot?->charging_state ?: '-' }}</strong>
                    </div>
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Energia adicionada</span>
                        <strong>{{ is_numeric($snapshot?->charge_energy_added) ? number_format((float) $snapshot->charge_energy_added, 2, ',', ' ') . ' kWh' : '-' }}</strong>
                    </div>
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Potencia do carregador</span>
                        <strong>{{ is_numeric($snapshot?->charger_power) ? number_format((float) $snapshot->charger_power, 1, ',', ' ') . ' kW' : '-' }}</strong>
                    </div>
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Limite SOC</span>
                        <strong>{{ $this->percentValue($snapshot?->charge_limit_soc) }}</strong>
                    </div>
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Temperatura interior / exterior</span>
                        <strong>{{ $this->temperatureValue($snapshot?->inside_temp) }} / {{ $this->temperatureValue($snapshot?->outside_temp) }}</strong>
                    </div>
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Ultimo custo conhecido</span>
                        <strong>{{ $this->moneyValue($latestCharge) }}</strong>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section heading="Pneus e localizacao">
                <div class="tesla-detail__grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                    @foreach ($this->tirePressureCards() as $card)
                        <div class="tesla-detail__card">
                            <div class="tesla-detail__label">{{ $card['label'] }}</div>
                            <div class="tesla-detail__value">{{ $card['value'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="tesla-detail__list" style="margin-top: 1rem;">
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Localidade Google</span>
                        <strong>{{ $snapshot?->locality ?: $snapshot?->formatted_address ?: 'Sem localidade Google' }}</strong>
                    </div>
                    <div class="tesla-detail__row">
                        <span class="tesla-detail__meta">Direcao / velocidade</span>
                        <strong>{{ $snapshot?->heading ?: '-' }} / {{ is_numeric($snapshot?->speed) ? number_format((float) $snapshot->speed, 1, ',', ' ') . ' mph' : '-' }}</strong>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    @if ($hasLocation)
                        <iframe
                            class="tesla-detail__map"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps?q={{ $mapQuery }}&z=15&output=embed"
                            title="Mapa Google da localizacao da viatura"
                        ></iframe>
                        <div style="margin-top: .6rem;">
                            <x-filament::button
                                tag="a"
                                href="https://www.google.com/maps/search/?api=1&query={{ $mapQuery }}"
                                target="_blank"
                                color="gray"
                                size="sm"
                                icon="heroicon-m-map-pin"
                            >
                                Abrir no Google Maps
                            </x-filament::button>
                        </div>
                    @else
                        <div class="tesla-detail__empty">Sem localizacao disponivel no ultimo snapshot.</div>
                    @endif
                </div>
            </x-filament::section>
        </div>

        <div class="tesla-detail__split">
            <x-filament::section heading="Snapshots km">
                @if ($manualSnapshots->isEmpty())
                    <div class="tesla-detail__empty">Ainda nao existem snapshots manuais de odometro.</div>
                @else
                    <div class="tesla-detail__list">
                        @foreach ($manualSnapshots as $manualSnapshot)
                            <div class="tesla-detail__row">
                                <span>
                                    <strong>{{ $manualSnapshot->recorded_at?->format('Y-m-d H:i') ?: '-' }}</strong>
                                    <br>
                                    <span class="tesla-detail__meta">
                                        {{ $manualSnapshot->weeklyMileage ? 'Semana ' . $manualSnapshot->weeklyMileage->period_start?->format('Y-m-d') . ' - ' . $manualSnapshot->weeklyMileage->period_end?->format('Y-m-d') : 'Snapshot manual' }}
                                    </span>
                                </span>
                                <strong>
                                    {{ $this->distanceValue($manualSnapshot->odometer) }}
                                    @if ($manualSnapshot->weeklyMileage)
                                        <br>
                                        <span class="tesla-detail__meta">{{ number_format((float) $manualSnapshot->weeklyMileage->weekly_km, 1, ',', ' ') }} km semana</span>
                                    @endif
                                </strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Ultimos carregamentos">
                @if ($chargingEvents->isEmpty())
                    <div class="tesla-detail__empty">Sem dados de abastecimentos/carregamentos.</div>
                @else
                    <div class="tesla-detail__list">
                        @foreach ($chargingEvents as $event)
                            <div class="tesla-detail__row">
                                <span>
                                    <strong>{{ $event->started_at?->format('Y-m-d H:i') ?: '-' }}</strong>
                                    <br>
                                    <span class="tesla-detail__meta">{{ $event->location_name ?: $event->source }}</span>
                                </span>
                                <strong>{{ is_numeric($event->energy_kwh) ? number_format((float) $event->energy_kwh, 2, ',', ' ') . ' kWh' : '-' }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Erros">
                @if ($errors->isEmpty())
                    <div class="tesla-detail__empty">Sem erros registados.</div>
                @else
                    <div class="tesla-detail__list">
                        @foreach ($errors as $error)
                            <div class="tesla-detail__row">
                                <span>
                                    <strong>{{ $error->code ?: $error->source }}</strong>
                                    <br>
                                    <span class="tesla-detail__meta">{{ $error->message ?: 'Sem detalhe' }}</span>
                                </span>
                                <strong>{{ $error->occurred_at?->format('Y-m-d H:i') ?: '-' }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section heading="Payload Tesla completo">
            @if ($rawPayload === [])
                <div class="tesla-detail__empty">Sem payload bruto guardado.</div>
            @else
                @foreach ($rawPayload as $section => $payload)
                    <details style="margin-bottom: .75rem;">
                        <summary style="cursor: pointer; font-weight: 700;">{{ $section }}</summary>
                        <pre class="tesla-detail__raw">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                @endforeach
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
