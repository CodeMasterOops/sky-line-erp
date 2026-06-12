<?php

use App\Services\Nepal\NepaliNumberService;

it('converts amounts to english words using lakh and crore grouping', function (float $amount, string $expected) {
    $service = new NepaliNumberService;

    expect($service->amountToWordsEn($amount))->toBe($expected);
})->with([
    'zero' => [0, 'Zero Rupees Only'],
    'one thousand two hundred' => [1200, 'One Thousand Two Hundred Rupees Only'],
    'five thousand' => [5000, 'Five Thousand Rupees Only'],
    'one lakh fifty thousand' => [150000, 'One Lakh Fifty Thousand Rupees Only'],
    'fifteen lakh' => [1500000, 'Fifteen Lakh Rupees Only'],
    'with paisa' => [1200.50, 'One Thousand Two Hundred Rupees and Fifty Paisa Only'],
]);
