<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aviso de pressao dos pneus</title>
</head>
<body style="margin: 0; background: #f3f4f6; color: #111827; font-family: Arial, sans-serif;">
    <div style="max-width: 640px; margin: 0 auto; padding: 32px 16px;">
        <div style="background: #ffffff; border-radius: 12px; padding: 28px;">
            <h1 style="margin: 0 0 20px; font-size: 22px;">Aviso de pressao dos pneus</h1>
            <p>Ola {{ $driver->name }},</p>
            <p>Detetamos pressoes que necessitam de verificacao na viatura <strong>{{ $vehicle->display_name ?: $vehicle->vin }}</strong>. O intervalo de referencia usado pela Zentrum e de <strong>42 a 43 PSI</strong>.</p>

            <table style="border-collapse: collapse; margin: 20px 0; width: 100%;">
                <thead>
                    <tr>
                        <th style="border-bottom: 1px solid #d1d5db; padding: 8px; text-align: left;">Pneu</th>
                        <th style="border-bottom: 1px solid #d1d5db; padding: 8px; text-align: right;">Pressao</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['fl' => 'Dianteiro esquerdo', 'fr' => 'Dianteiro direito', 'rl' => 'Traseiro esquerdo', 'rr' => 'Traseiro direito'] as $position => $label)
                        <tr>
                            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ $label }}</td>
                            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: right;">{{ number_format($assessment['pressures'][$position], 1, ',', ' ') }} PSI</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h2 style="font-size: 17px;">Problemas detetados</h2>
            <ul>
                @foreach ($assessment['problems'] as $problem)
                    <li style="margin-bottom: 6px;">{{ $problem }}</li>
                @endforeach
            </ul>

            <p><strong>Recomendamos que verifique e corrija a pressao antes de continuar a utilizacao regular da viatura.</strong></p>
            <p>Circular com pressao incorreta ou desequilibrada pode reduzir a aderencia e a eficacia da travagem, provocar aquecimento e deformacao do pneu, acelerar o desgaste irregular e aumentar o consumo de energia.</p>
            <p style="color: #4b5563; font-size: 13px;">O intervalo indicado neste aviso e uma referencia operacional da Zentrum e nao substitui as especificacoes do fabricante da viatura e dos pneus.</p>
        </div>
    </div>
</body>
</html>
