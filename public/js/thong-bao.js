/**
 * Bat/tat thong bao day cho khu quan tri.
 *
 * iPhone (iOS 16.4+) CHI cho dang ky khi trang da duoc "Them vao Man hinh
 * chinh" va mo tu bieu tuong do. Mo trong Safari thuong thi khong co
 * PushManager - khong phai loi cua minh, la gioi han cua Apple. Nut se noi ro
 * dieu do thay vi bao "khong ho tro" chung chung.
 */
(function () {
    var nut = document.getElementById('nut-thong-bao');

    if (!nut) {
        return;
    }

    var trangThai = document.getElementById('trang-thai-thong-bao');
    var khoaCongKhai = nut.dataset.khoa;
    var duongDangKy = nut.dataset.dangKy;
    var duongHuy = nut.dataset.huy;
    var csrf = nut.dataset.csrf;
    var dangCai = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var iOS = /iphone|ipad|ipod/i.test(navigator.userAgent);

    function noi(chu, kieu) {
        if (!trangThai) {
            return;
        }

        trangThai.textContent = chu;
        trangThai.className = 'alert ' + (kieu || 'alert-info');
    }

    function doiKhoa(chuoi) {
        var dem = '='.repeat((4 - (chuoi.length % 4)) % 4);
        var tho = window.atob((chuoi + dem).replace(/-/g, '+').replace(/_/g, '/'));
        var mang = new Uint8Array(tho.length);

        for (var i = 0; i < tho.length; i++) {
            mang[i] = tho.charCodeAt(i);
        }

        return mang;
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        nut.disabled = true;
        noi(
            iOS && !dangCai
                ? 'iPhone chỉ nhận được thông báo khi trang này được thêm vào Màn hình chính. Bấm nút Chia sẻ trong Safari, chọn "Thêm vào MH chính", rồi mở lại từ biểu tượng đó.'
                : 'Trình duyệt này không nhận được thông báo đẩy. Dùng Safari trên iPhone (đã thêm vào Màn hình chính) hoặc Chrome trên máy tính.',
            'alert-info'
        );

        return;
    }

    navigator.serviceWorker.register('/sw.js').then(function (dk) {
        return dk.pushManager.getSubscription().then(function (da) {
            capNhatNut(!!da);
        });
    }).catch(function (e) {
        noi('Không khởi động được nền thông báo: ' + e.message, 'alert-error');
    });

    function capNhatNut(dangBat) {
        nut.textContent = dangBat ? 'Tắt thông báo trên máy này' : 'Bật thông báo trên máy này';
        nut.dataset.bat = dangBat ? '1' : '0';

        if (dangBat) {
            noi('Máy này đang nhận thông báo.', 'alert-ok');
        } else if (Notification.permission === 'denied') {
            noi('Bạn đã từ chối quyền thông báo. Vào Cài đặt điện thoại, mục Thông báo của ứng dụng này để bật lại.', 'alert-error');
        } else {
            noi('Máy này chưa nhận thông báo. Bấm nút bên dưới để bật.', 'alert-info');
        }
    }

    nut.addEventListener('click', function () {
        nut.disabled = true;

        var dangBat = nut.dataset.bat === '1';
        var viec = dangBat ? tat() : bat();

        viec.catch(function (e) {
            noi('Không xong: ' + e.message, 'alert-error');
        }).then(function () {
            nut.disabled = false;
        });
    });

    function bat() {
        // Quyen phai xin trong mot cu bam that, khong xin luc tai trang duoc.
        return Notification.requestPermission().then(function (quyen) {
            if (quyen !== 'granted') {
                capNhatNut(false);
                throw new Error('bạn chưa cho phép hiện thông báo');
            }

            return navigator.serviceWorker.ready;
        }).then(function (dk) {
            return dk.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: doiKhoa(khoaCongKhai),
            });
        }).then(function (dk) {
            var j = dk.toJSON();

            return fetch(duongDangKy, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    endpoint: dk.endpoint,
                    p256dh: j.keys.p256dh,
                    auth: j.keys.auth,
                }),
            });
        }).then(function (tra) {
            if (!tra.ok) {
                throw new Error('máy chủ trả lỗi ' + tra.status);
            }

            capNhatNut(true);
        });
    }

    function tat() {
        return navigator.serviceWorker.ready.then(function (dk) {
            return dk.pushManager.getSubscription();
        }).then(function (dk) {
            if (!dk) {
                capNhatNut(false);

                return;
            }

            var dia = dk.endpoint;

            return dk.unsubscribe().then(function () {
                return fetch(duongHuy, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ endpoint: dia }),
                });
            }).then(function () {
                capNhatNut(false);
            });
        });
    }
})();
