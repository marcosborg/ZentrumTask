<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceTemplateValues([
            '325,00 € — trezentos e vinte e cinco euros' => '{{billing_profile.vehicle_rent_value}} € — {{billing_profile.vehicle_rent_value_in_words}}',
            '375,00 € — trezentos e setenta e cinco euros' => '{{billing_profile.vehicle_rent_value}} € — {{billing_profile.vehicle_rent_value_in_words}}',
            '2.000 quilómetros por semana' => '{{billing_profile.extra_km_limit}} quilómetros por semana',
            '2.500 quilómetros por semana' => '{{billing_profile.extra_km_limit}} quilómetros por semana',
            '0,12 € por quilómetro adicional' => '{{billing_profile.extra_km_rate}} € por quilómetro adicional',
            'Seguro válido até: 18-10-2026' => 'Seguro válido até: {{vehicle.insurance_expires_at}}',
            'Seguro válido até: 27/07/2026' => 'Seguro válido até: {{vehicle.insurance_expires_at}}',
            'Inspeção válida até: 29-06-2027' => 'Inspeção válida até: {{vehicle.inspection_expires_at}}',
            'Inspeção válida até: 10/12/2026' => 'Inspeção válida até: {{vehicle.inspection_expires_at}}',
            'Rio Meão, 16 de setembro de 2026' => 'Rio Meão, {{date}}',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $templates = DB::table('document_templates')
            ->whereIn('internal_name', ['Aluguer particular', 'Contrato para empresa'])
            ->get(['id', 'internal_name', 'content']);

        foreach ($templates as $template) {
            $content = (string) $template->content;
            $rent = $template->internal_name === 'Aluguer particular'
                ? '325,00 € — trezentos e vinte e cinco euros'
                : '375,00 € — trezentos e setenta e cinco euros';
            $kilometres = $template->internal_name === 'Aluguer particular'
                ? '2.000 quilómetros por semana'
                : '2.500 quilómetros por semana';
            $insurance = $template->internal_name === 'Aluguer particular'
                ? 'Seguro válido até: 18-10-2026'
                : 'Seguro válido até: 27/07/2026';
            $inspection = $template->internal_name === 'Aluguer particular'
                ? 'Inspeção válida até: 29-06-2027'
                : 'Inspeção válida até: 10/12/2026';

            $content = str_replace('{{billing_profile.vehicle_rent_value}} € — {{billing_profile.vehicle_rent_value_in_words}}', $rent, $content);
            $content = str_replace('{{billing_profile.extra_km_limit}} quilómetros por semana', $kilometres, $content);
            $content = str_replace('{{billing_profile.extra_km_rate}} € por quilómetro adicional', '0,12 € por quilómetro adicional', $content);
            $content = str_replace('Seguro válido até: {{vehicle.insurance_expires_at}}', $insurance, $content);
            $content = str_replace('Inspeção válida até: {{vehicle.inspection_expires_at}}', $inspection, $content);

            if ($template->internal_name === 'Aluguer particular') {
                $content = str_replace('Rio Meão, {{date}}', 'Rio Meão, 16 de setembro de 2026', $content);
            }

            DB::table('document_templates')->where('id', $template->id)->update(['content' => $content]);
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function replaceTemplateValues(array $replacements): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->whereIn('internal_name', ['Aluguer particular', 'Contrato para empresa'])
            ->get(['id', 'content'])
            ->each(function (object $template) use ($replacements): void {
                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update(['content' => str_replace(array_keys($replacements), array_values($replacements), (string) $template->content)]);
            });
    }
};
