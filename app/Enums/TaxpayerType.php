<?php

namespace App\Enums;

enum TaxpayerType: string
{
    case SelfEmployed = 'self_employed';
    case EmpresarioEmNomeIndividual = 'empresario_em_nome_individual';
    case Sociedade = 'sociedade';
    case Dependente = 'dependente';
    case Outro = 'outro';
}
