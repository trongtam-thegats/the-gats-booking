/**
 * Service worker cua khu quan tri: chi lam mot viec la hien thong bao day.
 * KHONG cache gi ca - cache sai o day la nhan vien nhin so lieu cu.
 */
self.addEventListener('push', function (event) {
    var tin = {};

    try {
        tin = event.data ? event.data.json() : {};
    } catch (e) {
        tin = { tieu_de: 'The Gats', noi_dung: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(tin.tieu_de || 'The Gats', {
            body: tin.noi_dung || '',
            icon: '/brand/icon-192.png',
            badge: '/brand/icon-192.png',
            tag: tin.nhan || undefined,
            data: { duong_dan: tin.duong_dan || '/quan-ly/dat-ban' },
        })
    );
});

// Bam vao thong bao: dua thang toi don do, dung lai tab dang mo neu co.
self.addEventListener('notificationclick', function (event) {
    var dich = (event.notification.data && event.notification.data.duong_dan) || '/quan-ly/dat-ban';
    event.notification.close();

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (ds) {
            for (var i = 0; i < ds.length; i++) {
                if ('focus' in ds[i]) {
                    ds[i].navigate(dich);
                    return ds[i].focus();
                }
            }

            return self.clients.openWindow(dich);
        })
    );
});
