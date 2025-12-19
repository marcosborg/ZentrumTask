<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Candidatura #{{ $record->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; margin: 0; padding: 0; background: #f8fafc; }
        .page { padding: 22px 26px; }
        .card { background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06); padding: 14px 16px; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
        .logo { height: 46px; }
        h1 { margin: 0; font-size: 21px; letter-spacing: 0.2px; color: #0f172a; }
        h2 { margin: 0 0 8px; font-size: 13px; color: #0f172a; letter-spacing: 0.2px; }
        .card-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; align-items: start; }
        .section { padding: 6px 0 0; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 14px; }
        .muted { color: #64748b; font-size: 10px; margin-bottom: 1px; }
        .value { font-weight: 700; color: #0f172a; font-size: 11px; }
        .pill { display: inline-block; padding: 4px 9px; border-radius: 999px; font-size: 9px; background: #e0f2fe; color: #075985; font-weight: 700; }
        .docs { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .doc-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 10px; background: #fff; box-shadow: inset 0 1px 0 rgba(255,255,255,0.7); }
        .doc-card h3 { margin: 0 0 4px; font-size: 12px; color: #0f172a; }
        .page-break { page-break-before: always; }
        .doc-gallery { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: start; }
        .doc-image { width: 100%; max-height: 460px; object-fit: contain; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; padding: 6px; }
        .missing { color: #b91c1c; font-size: 11px; font-weight: 600; }
        .meta { color: #0ea5e9; font-size: 10px; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="header">
                <div>
                    <h1>Candidatura #{{ $record->id }}</h1>
                    <div class="meta">Token: {{ $record->token }}</div>
                </div>
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="Zentrum TVDE">
                @endif
            </div>
            <div class="card-grid">
                <div class="section">
                    <h2>Dados pessoais</h2>
                    <div class="grid">
                        <div><div class="muted">Nome</div><div class="value">{{ $record->full_name ?? '-' }}</div></div>
                        <div><div class="muted">Email</div><div class="value">{{ $record->email ?? '-' }}</div></div>
                        <div><div class="muted">Telemovel</div><div class="value">{{ $record->phone ?? '-' }}</div></div>
                        <div><div class="muted">NIF</div><div class="value">{{ $record->nif ?? '-' }}</div></div>
                    </div>
                </div>
                <div class="section">
                    <h2>Estado</h2>
                    <div class="grid">
                        <div><div class="muted">Estado</div><div class="pill">{{ $record->status }}</div></div>
                        <div><div class="muted">Passo atual</div><div class="value">{{ $record->current_step ?? '-' }}</div></div>
                        <div><div class="muted">Criada em</div><div class="value">{{ optional($record->created_at)->format('Y-m-d H:i') ?? '-' }}</div></div>
                        <div><div class="muted">Submetida em</div><div class="value">{{ optional($record->submitted_at)->format('Y-m-d H:i') ?? '-' }}</div></div>
                    </div>
                </div>
                <div class="section">
                    <h2>Elegibilidade</h2>
                    <div class="grid">
                        <div><div class="muted">Tem curso TVDE?</div><div class="value">{{ $record->has_tvde_course ? 'Sim' : 'Nao' }}</div></div>
                        <div><div class="muted">Certificado valido?</div><div class="value">{{ $record->certificate_valid ? 'Sim' : 'Nao' }}</div></div>
                        <div><div class="muted">Experiencia</div><div class="value">{{ $record->experience ?? '-' }}</div></div>
                        <div><div class="muted">Plataformas</div><div class="value">{{ $record->platforms ? implode(', ', $record->platforms) : '-' }}</div></div>
                    </div>
                </div>
                <div class="section">
                    <h2>Documentos</h2>
                    <div class="docs">
                        @foreach ($documents as $doc)
                            <div class="doc-card">
                                <h3>{{ $doc['label'] }} @if($doc['name']) <span class="muted">({{ $doc['name'] }})</span>@endif</h3>
                                @if (! $doc['exists'])
                                    <div class="missing">Ficheiro em falta.</div>
                                @else
                                    <div class="muted">Tipo: {{ $doc['mime'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page page-break">
        <h2>Documentos anexos</h2>
        <div class="doc-gallery">
            @foreach ($documents as $doc)
                <div>
                    <h3 style="margin: 0 0 6px; font-size: 12px;">{{ $doc['label'] }}</h3>
                    @if (! $doc['exists'])
                        <div class="missing">Ficheiro nao encontrado.</div>
                    @elseif ($doc['is_image'])
                        <img class="doc-image" src="{{ $doc['data_uri'] }}" alt="{{ $doc['label'] }}">
                    @else
                        <div class="muted">Conteudo incorporado.</div>
                        <object data="{{ $doc['data_uri'] }}" type="{{ $doc['mime'] }}" style="width:100%; height:620px;">
                            <p>Visualizacao nao suportada.</p>
                        </object>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
