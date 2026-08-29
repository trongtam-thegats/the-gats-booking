<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Nem ra khi khong the giu ban: het bàn, ngoai gio, sai rang buoc dat truoc...
 * Thong diep cua exception nay duoc hien thang cho khach nen phai viet de hieu.
 */
class BookingUnavailableException extends RuntimeException
{
}
