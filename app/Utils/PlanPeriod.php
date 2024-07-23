<?php

namespace App\Utils;

enum PlanPeriod: string
{
    case ONETIME_PRICE = 'onetime_price';
    case MONTH_PRICE = 'month_price';
    case QUARTER_PRICE = 'quarter_price';
    case HALF_YEAR_PRICE = 'half_year_price';
    case YEAR_PRICE = 'year_price';
    case TWO_YEAR_PRICE = 'two_year_price';
    case THREE_YEAR_PRICE = 'three_year_price';

    case RESET_PRICE = 'reset_price';
}
