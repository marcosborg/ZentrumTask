<?php

namespace App\Support;

class ReservationOfferContent
{
    public static function taxMessage(): string
    {
        return 'Acresce IVA à taxa em vigor.';
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $baseAmount = (float) config('services.ifthenpay.initial_deposit_amount', 250);
        $vatRate = (float) config('services.ifthenpay.initial_deposit_vat_rate', 23);
        $vatAmount = round($baseAmount * ($vatRate / 100), 2);
        $totalAmount = round($baseAmount + $vatAmount, 2);

        return [
            'tax_message' => static::taxMessage(),
            'base_amount' => $baseAmount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
            'formatted_base_amount' => static::formatCurrency($baseAmount),
            'formatted_vat_amount' => static::formatCurrency($vatAmount),
            'formatted_total_amount' => static::formatCurrency($totalAmount),
            'weekly_deposit_increment' => static::formatCurrency(25),
            'retained_deposit_total' => static::formatCurrency(1000),
            'included_km' => '2000 km/semana',
            'extra_km_rate' => '0,12€ por km',
            'statement_deadline' => 'Segunda até às 11:00',
            'pickup_location' => 'Santa Maria da Feira',
            'pickup_transport_hint' => 'Quem vem de Lisboa pode viajar de Rede Expressos ou Flixbus até à central de camionagem de Santa Maria da Feira.',
            'sections' => [
                [
                    'title' => 'O que está incluído',
                    'items' => [
                        'Seguro contra todos os riscos.',
                        'Mudança de pneus.',
                        'Manutenções incluídas.',
                        'Reparações derivadas da normal utilização da viatura.',
                    ],
                ],
                [
                    'title' => 'Como funciona o aluguer e a caução',
                    'items' => [
                        'O valor de aluguer da viatura é descontado semanalmente aos valores obtidos na Uber e Bolt.',
                        'Para reservar, é obrigatório o pagamento inicial de '.static::formatCurrency($baseAmount).' de caução.',
                        'Nas 30 semanas seguintes são descontados '.static::formatCurrency(25).' adicionais, até perfazer '.static::formatCurrency(1000).' de caução retida.',
                        'A caução é devolvida quando a viatura é entregue de volta pelo motorista.',
                        'Estão incluídos 2000 km por semana.',
                        'Acima desse limite, o valor extra é de 0,12€ por km.',
                    ],
                ],
                [
                    'title' => 'Pagamentos e levantamentos',
                    'items' => [
                        'O extrato semanal é entregue até às 11:00 de cada segunda-feira.',
                        'O pagamento ao motorista é transferido de imediato após receção do recibo verde ou fatura.',
                        'A viatura deve ser levantada nas nossas instalações em Santa Maria da Feira, após agendamento.',
                        'Quem vem de Lisboa pode viajar de Rede Expressos ou Flixbus até à central de camionagem de Santa Maria da Feira.',
                    ],
                ],
            ],
        ];
    }

    protected static function formatCurrency(float $amount): string
    {
        return number_format($amount, 2, ',', '.').'€';
    }
}
