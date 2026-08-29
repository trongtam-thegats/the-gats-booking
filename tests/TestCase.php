<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test client cua Laravel tu gui "Accept-Language: en-us,en", nen trang
        // khach se mo ban tieng Anh - dung nhu thiet ke cho khach nuoc ngoai,
        // nhung lam cac test doc chu tieng Viet that bai. Ghim ve tieng Viet;
        // test nao can ban tieng Anh thi tu goi ?lang=en.
        $this->withHeader('Accept-Language', 'vi');
    }
}
