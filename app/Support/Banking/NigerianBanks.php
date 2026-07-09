<?php

declare(strict_types=1);

namespace App\Support\Banking;

final class NigerianBanks
{
    /** @return list<array{code: string, name: string}> */
    public static function all(): array
    {
        return [
            ['code' => '035', 'name' => 'Wema Bank (ALAT)'],
            ['code' => '058', 'name' => 'GTBank'],
            ['code' => '044', 'name' => 'Access Bank'],
            ['code' => '057', 'name' => 'Zenith Bank'],
            ['code' => '033', 'name' => 'UBA'],
            ['code' => '011', 'name' => 'First Bank'],
            ['code' => '032', 'name' => 'Union Bank'],
            ['code' => '214', 'name' => 'FCMB'],
            ['code' => '070', 'name' => 'Fidelity Bank'],
            ['code' => '221', 'name' => 'Stanbic IBTC'],
            ['code' => '232', 'name' => 'Sterling Bank'],
            ['code' => '082', 'name' => 'Keystone Bank'],
            ['code' => '076', 'name' => 'Polaris Bank'],
            ['code' => '101', 'name' => 'Providus Bank'],
            ['code' => '999992', 'name' => 'Opay'],
            ['code' => '999991', 'name' => 'PalmPay'],
            ['code' => '50515', 'name' => 'Moniepoint MFB'],
            ['code' => '999993', 'name' => 'Kuda Bank'],
        ];
    }
}
