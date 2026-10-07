<x-filament-panels::page>
    <div class="flex flex-col gap-6" wire:poll.3s>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Modo ativo</p>
                <p class="font-semibold">{{ config('database.mode') === 'production' ? 'Producao AWS' : 'Sandbox local' }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Producao AWS Lightsail</p>
                <p class="font-semibold">{{ config('database.replication.production_dump.lightsail_instance') }}</p>
                <p class="text-sm">{{ config('database.profiles.production.database') }}</p>
                @php($diagnostic = $this->diagnostic())
                <p class="text-sm">{{ ($diagnostic['status'] ?? '') === 'completed' ? 'Ligacao validada no ultimo diagnostico' : 'Ligacao por validar' }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ config('database-management.local') ? 'Sandbox neste computador' : 'Ambiente do servidor' }}</p>
                <p class="font-semibold">{{ config('database-management.local') ? config('database.profiles.sandbox.database') : 'Producao fixa' }}</p>
            </div>
        </div>

        @php($operation = $this->operation())
        @if ($operation)
            <div class="flex flex-col gap-2 rounded-xl border border-gray-200 p-4 dark:border-gray-700" role="status" aria-live="polite">
                <p class="font-semibold">
                    {{ ['pending' => 'Pendente', 'running' => 'Em execucao', 'completed' => 'Concluido', 'failed' => 'Falhou'][$operation['status']] ?? 'Estado desconhecido' }}
                </p>
                <p>{{ $operation['message'] }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $operation['source'] === 'production' ? 'Producao AWS' : 'Sandbox local' }}
                    @if ($operation['target'])
                        → {{ $operation['target'] === 'production' ? 'Producao AWS' : 'Sandbox local' }}
                    @endif
                </p>
                @if ($operation['backup'])
                    <p class="text-sm">Backup privado: {{ basename($operation['backup']['path']) }}. Utilize “Descarregar backup”.</p>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">A ligacao ainda nao foi verificada nesta pagina. Utilize “Verificar ligacao AWS”.</p>
        @endif

        @if ($this->latestBackup() && ! ($operation['backup'] ?? null))
            <p class="text-sm">Ultimo backup privado disponivel: {{ basename($this->latestBackup()['path']) }}.</p>
        @endif

        <p class="text-sm text-gray-600 dark:text-gray-300">
            @if (config('database-management.local'))
                As copias usam SSH para aceder a AWS e criam um backup do destino antes da substituicao.
                Alternar para producao permite trabalhar diretamente sobre os dados reais da AWS.
            @else
                Este servidor permanece em producao. A alternancia e as copias entre ambientes sao geridas no computador local.
            @endif
        </p>
    </div>
</x-filament-panels::page>
