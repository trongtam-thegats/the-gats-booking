<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CustomerInsightController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiningTableController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PublicBookingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Khu quan tri - chi mo tren ten mien quan tri (vi du booking.thegats.vn)
|--------------------------------------------------------------------------
|
| Khai bao truoc phan cua khach de tien to "quan-ly" luon duoc uu tien.
| Nhan vien vao day nhin duoc ca chuoi; khach vao ten mien cua mot quan thi
| khong cham toi duoc khu nay.
|
*/

Route::prefix('quan-ly')->name('admin.')->middleware('admin.site')->group(function () {
    Route::get('dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('dang-nhap', [AuthController::class, 'login'])->name('login.submit');

    Route::middleware(['auth', 'role', 'password.change'])->group(function () {
        Route::post('dang-xuat', [AuthController::class, 'logout'])->name('logout');

        // Doi mat khau cua chinh minh. Tai khoan con dung mat khau khoi tao thi
        // moi duong dan khac deu bi day ve day (xem EnsurePasswordChanged).
        Route::get('doi-mat-khau', [PasswordController::class, 'edit'])->name('password.edit');
        Route::post('doi-mat-khau', [PasswordController::class, 'update'])->name('password.update');

        // Xem lich dat ban - moi vai tro deu vao duoc, ke ca vai chi xem
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('so-do-ban', [FloorController::class, 'index'])->name('floor');
        Route::get('dat-ban', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::get('dat-ban/{booking}', [AdminBookingController::class, 'show'])
            ->where('booking', '[A-Za-z0-9]+')->name('bookings.show');
        Route::get('khach', [GuestController::class, 'index'])->name('guests.index');

        // Xu ly dat ban va xem phan tich - quan tri va quan ly
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('dat-ban/tao-moi', [AdminBookingController::class, 'create'])->name('bookings.create');

            // Bao cao va du lieu ban hang: vai chi xem khong dung toi.
            Route::get('bao-cao', [ReportController::class, 'index'])->name('reports.index');
            Route::get('hoa-don', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('khach-hang', [CustomerInsightController::class, 'index'])->name('customers.index');
            Route::get('khach-hang/{phone}', [CustomerInsightController::class, 'show'])
                ->where('phone', '[0-9+]+')->name('customers.show');

            Route::post('dat-ban', [AdminBookingController::class, 'store'])->name('bookings.store');
            Route::post('dat-ban/{booking}/xac-nhan', [AdminBookingController::class, 'confirm'])->name('bookings.confirm');
            Route::post('dat-ban/{booking}/huy', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');
            Route::post('dat-ban/{booking}/trang-thai/{action}', [AdminBookingController::class, 'transition'])->name('bookings.transition');
            Route::post('dat-ban/{booking}/ban', [AdminBookingController::class, 'assignTables'])->name('bookings.tables');
            Route::post('dat-ban/{booking}/ghi-chu', [AdminBookingController::class, 'updateNote'])->name('bookings.note');
            Route::post('dat-ban/{booking}/doi-lich', [AdminBookingController::class, 'reschedule'])->name('bookings.reschedule');
            Route::post('khach/ghi-chu', [GuestController::class, 'saveNote'])->name('guests.note');

            // Tra nhanh ten khach theo so dien thoai cho form dat ban ho khach.
            // Chi mo trong nhom nay - dua ra trang khach thi bat ky ai cung do
            // duoc ten cua toan bo khach hang.
            Route::get('khach/tra-nhanh', [GuestController::class, 'quickLookup'])
                ->middleware('throttle:60,1')
                ->name('guests.quick');
            Route::post('khach-hang/{phone}/danh-dau', [CustomerInsightController::class, 'review'])
                ->where('phone', '[0-9+]+')->name('customers.review');

        });

        // Cau hinh - chi quan tri (user chot 2026-08-31)
        Route::middleware('role:admin')->group(function () {
            // Xoa han dat ban de bao cao va phan tich khach khong bi don sai lam
            // lech. Chi quan tri, va moi lan xoa deu de lai mot dong nhat ky.
            // "nhat-ky-xoa" co dau gach nen khong dinh vao route dat-ban/{booking}
            // o tren (route do rang buoc [A-Za-z0-9]+).
            Route::get('dat-ban/nhat-ky-xoa', [AdminBookingController::class, 'deletionLog'])->name('bookings.deletions');
            Route::delete('dat-ban/{booking}', [AdminBookingController::class, 'destroy'])->name('bookings.destroy');

            // Khai bao dia diem, gio mo, khu vuc, ban
            Route::get('chi-nhanh', [BranchController::class, 'index'])->name('branches.index');
            Route::get('chi-nhanh/tao-moi', [BranchController::class, 'create'])->name('branches.create');
            Route::post('chi-nhanh', [BranchController::class, 'store'])->name('branches.store');
            Route::get('chi-nhanh/{branch}', [BranchController::class, 'edit'])->name('branches.edit');
            Route::put('chi-nhanh/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::delete('chi-nhanh/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
            Route::post('chi-nhanh/{branch}/lich-nghi', [BranchController::class, 'storeClosure'])->name('branches.closures.store');
            Route::delete('chi-nhanh/{branch}/lich-nghi/{closure}', [BranchController::class, 'destroyClosure'])->name('branches.closures.destroy');

            Route::get('noi-dung', [ContentController::class, 'index'])->name('content.index');
            Route::put('noi-dung/{brand}', [ContentController::class, 'update'])->name('content.update');

            Route::get('ban', [DiningTableController::class, 'index'])->name('tables.index');
            Route::post('ban/{branch}/khu-vuc', [DiningTableController::class, 'storeArea'])->name('areas.store');
            Route::put('ban/{branch}/khu-vuc/{area}', [DiningTableController::class, 'updateArea'])->name('areas.update');
            Route::delete('ban/{branch}/khu-vuc/{area}', [DiningTableController::class, 'destroyArea'])->name('areas.destroy');
            Route::post('ban/{branch}', [DiningTableController::class, 'store'])->name('tables.store');
            Route::post('ban/{branch}/hang-loat', [DiningTableController::class, 'bulkStore'])->name('tables.bulk');
            Route::put('ban/{branch}/{table}', [DiningTableController::class, 'update'])->name('tables.update');
            Route::delete('ban/{branch}/{table}', [DiningTableController::class, 'destroy'])->name('tables.destroy');

            Route::get('quan', [BrandController::class, 'index'])->name('brands.index');
            Route::post('quan', [BrandController::class, 'store'])->name('brands.store');
            Route::put('quan/{brand}', [BrandController::class, 'update'])->name('brands.update');
            Route::delete('quan/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

            // Tai tep xuat tu POS len de nhap, thay cho viec goi lenh tren may chu.
            Route::post('hoa-don/nhap', [InvoiceController::class, 'import'])->name('invoices.import');

            Route::get('cai-dat', [SettingController::class, 'index'])->name('settings.index');
            Route::put('cai-dat', [SettingController::class, 'update'])->name('settings.update');
            Route::post('cai-dat/gui-thu', [SettingController::class, 'test'])->name('settings.test');

            Route::get('tai-khoan', [UserController::class, 'index'])->name('users.index');
            Route::post('tai-khoan', [UserController::class, 'store'])->name('users.store');
            Route::put('tai-khoan/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('tai-khoan/{user}/dat-lai-mat-khau', [UserController::class, 'resetPassword'])->name('users.reset');
            Route::delete('tai-khoan/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Trang khach - moi ten mien la mot quan
|--------------------------------------------------------------------------
|
| booking.gemination.vn va booking.drinkinghealing.com deu chay chung bo
| route nay; middleware brand.site quyet dinh dang phuc vu quan nao.
|
*/

Route::middleware(['brand.site', 'guest.locale', 'guest.nguon'])->group(function () {
    Route::get('/', [PublicBookingController::class, 'index'])->name('home');
    // Tra cuu can dung ca ma lan so dien thoai, nen kho do; tran o day de chan
    // viec do hang loat, con nguong thi de rong hon nhu cau that cua mot nguoi.
    Route::get('/tra-cuu', [PublicBookingController::class, 'lookup'])
        ->middleware('throttle:20,1')
        ->name('booking.lookup');

    Route::get('/ma/{booking}', [PublicBookingController::class, 'show'])->name('booking.show');

    Route::post('/ma/{booking}/huy', [PublicBookingController::class, 'cancel'])
        ->middleware('throttle:huy-dat-ban')
        ->name('booking.cancel');

    Route::post('/dat-ban/{branch}', [PublicBookingController::class, 'store'])
        ->middleware('throttle:dat-ban')
        ->name('booking.store');

    // Khach doi ngay va so nguoi lien tuc trong luc chon, moi lan la mot lan
    // goi. Nguong phai du rong cho mot buoi chon that, chi chan viec cao du lieu.
    Route::get('/api/{branch}/khung-gio', [PublicBookingController::class, 'slots'])
        ->middleware('throttle:60,1')
        ->name('booking.slots');
});
