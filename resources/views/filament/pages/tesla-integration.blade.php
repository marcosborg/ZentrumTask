<x-filament-panels::page>
    <style>
        .tesla-admin {
            display: grid;
            gap: 1.25rem;
        }

        .tesla-admin__header {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }

        .tesla-admin__subtitle {
            color: rgb(156 163 175);
            font-size: .925rem;
            margin-top: .25rem;
        }

        .tesla-admin__actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
        }

        .tesla-admin__alert {
            border-radius: .75rem;
            border: 1px solid color-mix(in srgb, var(--alert-color) 35%, transparent);
            background: color-mix(in srgb, var(--alert-color) 12%, transparent);
            color: var(--alert-color);
            font-size: .925rem;
            padding: .875rem 1rem;
        }

        .tesla-admin__alert--success {
            --alert-color: rgb(34 197 94);
        }

        .tesla-admin__alert--danger {
            --alert-color: rgb(248 113 113);
        }

        .tesla-admin__stats {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .tesla-admin__stat {
            background: color-mix(in srgb, currentColor 4%, transparent);
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .875rem;
            padding: 1rem;
        }

        .tesla-admin__stat-label {
            color: rgb(156 163 175);
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tesla-admin__stat-value {
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1.1;
            margin-top: .6rem;
        }

        .tesla-admin__account-table {
            border-collapse: separate;
            border-spacing: 0;
            font-size: .875rem;
            overflow: hidden;
            width: 100%;
        }

        .tesla-admin__account-table th {
            color: rgb(156 163 175);
            font-size: .75rem;
            font-weight: 700;
            padding: .75rem;
            text-align: left;
            text-transform: uppercase;
        }

        .tesla-admin__account-table td {
            border-top: 1px solid rgba(148, 163, 184, .18);
            padding: .85rem .75rem;
            vertical-align: top;
        }

        .tesla-admin__muted {
            color: rgb(156 163 175);
        }

        .tesla-admin__scopes {
            color: rgb(209 213 219);
            max-width: 42rem;
            white-space: normal;
        }

        @media (max-width: 900px) {
            .tesla-admin__header {
                display: grid;
            }

            .tesla-admin__actions {
                justify-content: flex-start;
            }

            .tesla-admin__stats {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="tesla-admin">
        <div class="tesla-admin__header">
            <div>
                <p class="tesla-admin__subtitle">Gestao da ligacao OAuth e sincronizacao da frota Tesla.</p>
            </div>

            <div class="tesla-admin__actions">
                <x-filament::button tag="a" href="{{ route('admin.tesla.connect') }}" icon="heroicon-m-link">
                    Ligar conta Tesla
                </x-filament::button>

                <form method="post" action="{{ route('admin.tesla.syncVehicles') }}">
                    @csrf
                    <x-filament::button type="submit" color="gray" icon="heroicon-m-arrow-path">
                        Sincronizar veiculos
                    </x-filament::button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="tesla-admin__alert tesla-admin__alert--success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="tesla-admin__alert tesla-admin__alert--danger">
                {{ session('error') }}
            </div>
        @endif

        @unless ($isConfigured)
            <div class="tesla-admin__alert tesla-admin__alert--danger">
                Configuracao Tesla incompleta. Define TESLA_CLIENT_ID, TESLA_CLIENT_SECRET e TESLA_REDIRECT_URI no .env.
            </div>
        @endunless

        <x-filament::section>
            <div class="tesla-admin__stats">
                <div class="tesla-admin__stat">
                    <div class="tesla-admin__stat-label">Configuracao</div>
                    <div class="tesla-admin__stat-value">{{ $isConfigured ? 'Pronta' : 'Incompleta' }}</div>
                </div>

                <div class="tesla-admin__stat">
                    <div class="tesla-admin__stat-label">Contas ligadas</div>
                    <div class="tesla-admin__stat-value">{{ $accounts->count() }}</div>
                </div>

                <div class="tesla-admin__stat">
                    <div class="tesla-admin__stat-label">Veiculos sincronizados</div>
                    <div class="tesla-admin__stat-value">{{ $vehicles->count() }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Conta Tesla">
            @if ($accounts->isEmpty())
                <p class="tesla-admin__muted">Ainda nao existe nenhuma conta Tesla ligada.</p>
            @else
                <div style="overflow-x: auto;">
                    <table class="tesla-admin__account-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Scopes</th>
                                <th>Expira em</th>
                                <th>Ultima sincronizacao</th>
                                <th>Veiculos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                <tr>
                                    <td>{{ $account->id }}</td>
                                    <td>{{ $account->owner_email ?: $account->email ?: 'unknown' }}</td>
                                    <td class="tesla-admin__scopes">{{ implode(', ', $account->scopes ?? []) }}</td>
                                    <td>{{ $account->expires_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>{{ $account->last_synced_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>{{ $account->vehicles_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Veiculos Tesla">
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
