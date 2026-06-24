<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Integracao Tesla</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
        }

        main {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 32px 0;
        }

        header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            color: #0f172a;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .button.primary {
            border-color: #111827;
            background: #111827;
            color: #fff;
        }

        .status,
        section {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }

        .status {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1px;
            overflow: hidden;
            margin-bottom: 18px;
            background: #e2e8f0;
        }

        .status div,
        section {
            padding: 18px;
        }

        .status div {
            background: #fff;
        }

        .label {
            margin-bottom: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .value {
            font-size: 20px;
            font-weight: 700;
        }

        .notice {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
        }

        .notice.error {
            border-color: #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .notice.success {
            border-color: #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }

        .stack {
            display: grid;
            gap: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .empty {
            color: #64748b;
        }

        @media (max-width: 760px) {
            header,
            .actions {
                display: grid;
                justify-content: stretch;
            }

            .status {
                grid-template-columns: 1fr;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <main>
        <header>
            <div>
                <h1>Integracao Tesla</h1>
                <p class="label">Gestao da ligacao OAuth e sincronizacao da frota Tesla.</p>
            </div>

            <div class="actions">
                <a class="button primary" href="{{ route('admin.tesla.connect') }}">Ligar conta Tesla</a>

                <form method="post" action="{{ route('admin.tesla.syncVehicles') }}">
                    @csrf
                    <button class="button" type="submit">Sincronizar veiculos</button>
                </form>
            </div>
        </header>

        @if (session('success'))
            <div class="notice success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="notice error">{{ session('error') }}</div>
        @endif

        @unless ($isConfigured)
            <div class="notice error">
                Configuracao Tesla incompleta. Define TESLA_CLIENT_ID, TESLA_CLIENT_SECRET e TESLA_REDIRECT_URI no .env.
            </div>
        @endunless

        <div class="status">
            <div>
                <div class="label">Configuracao</div>
                <div class="value">{{ $isConfigured ? 'Pronta' : 'Incompleta' }}</div>
            </div>
            <div>
                <div class="label">Contas ligadas</div>
                <div class="value">{{ $accounts->count() }}</div>
            </div>
            <div>
                <div class="label">Veiculos sincronizados</div>
                <div class="value">{{ $vehicles->count() }}</div>
            </div>
        </div>

        <div class="stack">
            <section>
                <h2>Contas Tesla</h2>

                @if ($accounts->isEmpty())
                    <p class="empty">Ainda nao existe nenhuma conta Tesla ligada.</p>
                @else
                    <table>
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
                                    <td>{{ implode(', ', $account->scopes ?? []) }}</td>
                                    <td>{{ $account->expires_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>{{ $account->last_synced_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>{{ $account->vehicles_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section>
                <h2>Veiculos Tesla</h2>

                @if ($vehicles->isEmpty())
                    <p class="empty">Ainda nao existem veiculos Tesla sincronizados.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>VIN</th>
                                <th>Nome</th>
                                <th>Estado</th>
                                <th>Modelo</th>
                                <th>Odometro</th>
                                <th>Bateria</th>
                                <th>Ultima atualizacao</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicles as $vehicle)
                                <tr>
                                    <td>{{ $vehicle->vin }}</td>
                                    <td>{{ $vehicle->display_name ?: '-' }}</td>
                                    <td>{{ $vehicle->state ?: '-' }}</td>
                                    <td>{{ $vehicle->model ?: '-' }}</td>
                                    <td>{{ $vehicle->odometer !== null ? number_format((float) $vehicle->odometer, 1, ',', ' ') : '-' }}</td>
                                    <td>{{ $vehicle->battery_level !== null ? $vehicle->battery_level.'%' : '-' }}</td>
                                    <td>{{ $vehicle->last_seen_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    </main>
</body>
</html>
