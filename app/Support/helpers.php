<?php

use App\Support\Resources;

function hub_money(int $cents): string
{
    return Resources::money($cents);
}
function hub_hours(int $seconds): string
{
    return Resources::hours($seconds);
}
function hub_label(string $value): string
{
    return Resources::label($value);
}
