<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Use o botão acima para descarregar o backup mais recente guardado em
            <code>{{ config('database.backup.path', 'backups/database') }}</code> no disco
            <code>{{ config('database.backup.disk', 'local') }}</code>.
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Os backups são criados pelo comando <code>php artisan db:backup</code>.
        </p>
    </div>
</x-filament-panels::page>
