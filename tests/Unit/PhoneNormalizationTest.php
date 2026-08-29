<?php

namespace Tests\Unit;

use App\Services\Notifications\SmsChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizationTest extends TestCase
{
    /** @return array<int, array{0: ?string, 1: ?string}> */
    public static function phoneProvider(): array
    {
        return [
            ['0912345678', '84912345678'],
            ['09 1234 5678', '84912345678'],
            ['+84912345678', '84912345678'],
            ['(091) 234-5678', '84912345678'],
            ['84912345678', '84912345678'],
            [null, null],
            ['', null],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function test_chuan_hoa_so_dien_thoai_ve_dinh_dang_quoc_te(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, (new SmsChannel)->normalizePhone($input));
    }
}
