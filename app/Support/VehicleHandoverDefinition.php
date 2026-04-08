<?php

namespace App\Support;

class VehicleHandoverDefinition
{
    /**
     * @return array<int, array{key: string, label: string, requires_value?: bool, value_label?: string, value_type?: string}>
     */
    public static function checklistItems(): array
    {
        return [
            ['key' => 'photos_inside_outside', 'label' => 'Fotografias interior e exterior'],
            ['key' => 'battery_minimum_agreed', 'label' => 'Bateria minima acordada (>80%)', 'requires_value' => true, 'value_label' => 'Percentagem acordada', 'value_type' => 'percent'],
            ['key' => 'prio_card_inside', 'label' => 'Cartao PRIO dentro do carro'],
            ['key' => 'via_verde_installed', 'label' => 'Via Verde instalada'],
            ['key' => 'charging_cable', 'label' => 'Cabo de carregamento'],
            ['key' => 'charger', 'label' => 'Carregador'],
            ['key' => 'charger_bag', 'label' => 'Bolsa de carregador'],
            ['key' => 'registration_document', 'label' => 'Documento unico'],
            ['key' => 'insurance', 'label' => 'Seguro'],
            ['key' => 'tvde_inspection', 'label' => 'Inspecao TVDE'],
            ['key' => 'registered_in_app', 'label' => 'Viatura e motorista registados na aplicacao'],
            ['key' => 'tow_hook', 'label' => 'Gancho de reboque'],
            ['key' => 'clean_vehicle', 'label' => 'Carro limpo (interior e exterior)'],
            ['key' => 'software_update', 'label' => 'Atualizacao do software'],
            ['key' => 'phone_charger_base', 'label' => 'Base de carregador para telemovel'],
            ['key' => 'tire_pressure', 'label' => 'Pressao dos pneus'],
            ['key' => 'rules_explained', 'label' => 'Regras explicadas ao motorista'],
            ['key' => 'keys_received', 'label' => 'Motorista confirma a rececao da chave'],
            ['key' => 'warning_triangle', 'label' => 'Triangulo'],
            ['key' => 'safety_vest', 'label' => 'Colete'],
            ['key' => 'fire_extinguisher', 'label' => 'Extintor'],
            ['key' => 'first_aid_kit', 'label' => 'Kit primeiros socorros'],
            ['key' => 'tvde_stickers', 'label' => 'Disticos TVDE'],
            ['key' => 'deposit_paid', 'label' => 'Valor da caucao pago', 'requires_value' => true, 'value_label' => 'Valor pago', 'value_type' => 'currency'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, view: string, required: bool}>
     */
    public static function guidedPhotoZones(): array
    {
        return [
            ['key' => 'front_full', 'label' => 'Frente', 'view' => 'front', 'required' => true],
            ['key' => 'left_front', 'label' => 'Lado esquerdo frente', 'view' => 'left', 'required' => true],
            ['key' => 'left_rear', 'label' => 'Lado esquerdo tras', 'view' => 'left', 'required' => true],
            ['key' => 'rear_full', 'label' => 'Tras', 'view' => 'rear', 'required' => true],
            ['key' => 'right_front', 'label' => 'Lado direito frente', 'view' => 'right', 'required' => true],
            ['key' => 'right_rear', 'label' => 'Lado direito tras', 'view' => 'right', 'required' => true],
            ['key' => 'interior_dashboard', 'label' => 'Tablier / posto de conducao', 'view' => 'interior', 'required' => true],
            ['key' => 'interior_front_seats', 'label' => 'Bancos da frente', 'view' => 'interior', 'required' => true],
            ['key' => 'interior_rear_seats', 'label' => 'Bancos traseiros', 'view' => 'interior', 'required' => true],
            ['key' => 'boot', 'label' => 'Mala / bagageira', 'view' => 'rear', 'required' => false],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function damageTypes(): array
    {
        return ['risco', 'mossa', 'partido', 'outro'];
    }

    /**
     * @return array<int, string>
     */
    public static function vehicleZones(): array
    {
        return [
            'frente',
            'tras',
            'lado_esquerdo',
            'lado_direito',
            'interior',
            'tejadilho',
            'jantes',
            'vidros',
            'outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'delivery' => 'Entrega',
            'return' => 'Devolucao',
        ];
    }
}
