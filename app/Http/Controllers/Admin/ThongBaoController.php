<?php

namespace App\Http\Controllers\Admin;

use App\Models\PushSubscription;
use App\Services\ThongBaoDayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bat thong bao day cho tung thiet bi cua nhan vien.
 *
 * Moi thiet bi tu dang ky mot dia chi rieng voi trinh duyet roi gui ve day.
 * Dia chi gan theo tai khoan, nho vay moi nguoi chi nhan tin cua quan minh.
 */
class ThongBaoController extends AdminController
{
    public function index(Request $request)
    {
        return view('admin.thong-bao.index', [
            'thietBi' => $request->user()->pushSubscriptions()->latest()->get(),
            'khoa' => (string) config('booking.push.public_key'),
        ]);
    }

    public function dangKy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url'],
            'p256dh' => ['required', 'string', 'max:200'],
            'auth' => ['required', 'string', 'max:100'],
        ]);

        // Cung mot thiet bi dang ky lai (doi tai khoan, cai lai) thi ghi de.
        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            $data + [
                'user_id' => $request->user()->id,
                'thiet_bi' => mb_substr((string) $request->userAgent(), 0, 255),
                'lan_cuoi_ok' => null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function huy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        PushSubscription::where('endpoint', $data['endpoint'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Nut "Gui thu" de nhan vien biet chac dien thoai minh keu. */
    public function guiThu(Request $request, ThongBaoDayService $thongBao)
    {
        $so = $thongBao->guiToi(collect([$request->user()]), [
            'tieu_de' => 'Thử thông báo',
            'noi_dung' => 'Nếu bạn thấy tin này thì máy đã nhận thông báo bình thường.',
            'duong_dan' => route('admin.thong-bao.index'),
            'nhan' => 'thu-'.$request->user()->id,
        ]);

        return back()->with('status', $so > 0
            ? 'Đã gửi tới '.$so.' thiết bị. Kiểm tra điện thoại.'
            : 'Chưa gửi được: tài khoản này chưa có thiết bị nào bật thông báo.');
    }
}
