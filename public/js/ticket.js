/**
 * Ve anh xac nhan dat ban bang canvas roi luu ve may khach.
 *
 * Vi sao khong chup lai DOM: khong dung thu vien ngoai (hosting khong co
 * buoc build), va anh nay can co bo cuc rieng cho man hinh doc de khach gui
 * qua tin nhan. Ve tay bang canvas cho ket qua on dinh hon tren moi trinh duyet.
 *
 * Tren dien thoai uu tien navigator.share vi iOS khong luu duoc <a download>.
 */
(function () {
    'use strict';

    var node = document.getElementById('ticket-data');
    var button = document.getElementById('save-ticket');
    var errorBox = document.getElementById('save-ticket-error');

    if (!node || !button) {
        return;
    }

    var data;
    try {
        data = JSON.parse(node.textContent);
    } catch (e) {
        return;
    }

    // Trinh duyet qua cu thi khong hien nut, khach van chup man hinh duoc.
    var canvasProbe = document.createElement('canvas');
    if (!canvasProbe.getContext || !canvasProbe.toBlob) {
        return;
    }

    button.hidden = false;

    var WIDTH = 1080;
    var PAD = 88;
    var CONTENT = WIDTH - PAD * 2;

    var FALLBACK_FONT = '"Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif';

    function cssFont(name) {
        var value = getComputedStyle(document.body).getPropertyValue(name).trim();
        return value || FALLBACK_FONT;
    }

    /**
     * ctx.font bo qua chuoi khong hop le mot cach im lang, giu nguyen font cu.
     * Kiem tra lai sau khi gan de chac chan da doi, neu khong thi dung font he thong.
     */
    function setFont(ctx, weight, size, stack) {
        ctx.font = weight + ' ' + size + 'px ' + stack;
        if (ctx.font.indexOf(size + 'px') === -1) {
            ctx.font = weight + ' ' + size + 'px ' + FALLBACK_FONT;
        }
    }

    function setSpacing(ctx, value) {
        try {
            ctx.letterSpacing = value;
        } catch (e) {
            // Truoc Safari 17.4 khong co thuoc tinh nay, bo qua.
        }
    }

    function wrap(ctx, text, maxWidth) {
        var words = String(text).split(/\s+/);
        var lines = [];
        var line = '';

        for (var i = 0; i < words.length; i++) {
            var candidate = line ? line + ' ' + words[i] : words[i];

            if (line && ctx.measureText(candidate).width > maxWidth) {
                lines.push(line);
                line = words[i];
            } else {
                line = candidate;
            }
        }

        if (line) {
            lines.push(line);
        }

        return lines.length ? lines : [''];
    }

    function luminance(hex) {
        var m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
        if (!m) {
            return 0;
        }
        var n = parseInt(m[1], 16);
        return (0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function loadLogo() {
        if (!data.logo) {
            return Promise.resolve(null);
        }

        return new Promise(function (resolve) {
            var img = new Image();
            // Anh cung nguon nen canvas khong bi "nhuom ban", van xuat PNG duoc.
            img.onload = function () { resolve(img); };
            img.onerror = function () { resolve(null); };
            img.src = data.logo;
        });
    }

    /**
     * Ve mot lan de do chieu cao (draw = false), roi ve that voi dung chieu cao do.
     * Canvas khong tu co gian nen phai biet truoc.
     */
    function paint(ctx, logo, draw) {
        var dark = luminance(data.ground) < 0.5;
        var ink = dark ? '#f4efe6' : '#16130f';
        var muted = dark ? 'rgba(244,239,230,0.58)' : 'rgba(22,19,15,0.58)';
        var hairline = dark ? 'rgba(244,239,230,0.14)' : 'rgba(22,19,15,0.14)';

        var display = cssFont('--font-display');
        var body = cssFont('--font-body');
        var y = 0;

        function text(str, font, size, weight, color, spacing, lineHeight) {
            setFont(ctx, weight, size, font);
            setSpacing(ctx, spacing || '0px');

            var lines = wrap(ctx, str, CONTENT);
            var step = lineHeight || Math.round(size * 1.3);

            for (var i = 0; i < lines.length; i++) {
                y += step;
                if (draw) {
                    ctx.fillStyle = color;
                    ctx.fillText(lines[i], WIDTH / 2, y);
                }
            }

            setSpacing(ctx, '0px');
        }

        if (draw) {
            // Khong ve vien ngoai: ban ve o may chu (TicketImage) cung khong co,
            // hai anh phai giong het nhau vi khach nhin thay ca hai.
            ctx.fillStyle = data.ground;
            ctx.fillRect(0, 0, WIDTH, ctx.canvas.height);
        }

        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';

        y = 96;

        if (logo) {
            var h = Math.min(104, logo.naturalHeight || 104);
            var w = (logo.naturalWidth || 1) * (h / (logo.naturalHeight || 1));

            if (w > 520) {
                h = h * (520 / w);
                w = 520;
            }

            if (draw) {
                ctx.drawImage(logo, (WIDTH - w) / 2, y, w, h);
            }
            y += h + 46;
        } else {
            text(data.brand, display, 46, '600', ink, '2px');
            y += 20;
        }

        // Ma dat ban o dau ve nhung co chu ngang voi cac dong thong tin ben duoi,
        // giu font tieu de cua quan va net nhat cho nhe nhang.
        text(String(data.codeLabel).toUpperCase(), body, 26, '600', muted, '6px');
        y += 4;
        text(data.code, display, 46, '400', data.accent, '6px', 58);
        y += 26;

        // Vien trang thai
        setFont(ctx, '500', 27, body);
        var pillW = ctx.measureText(data.status).width + 64;
        if (draw) {
            ctx.strokeStyle = hairline;
            ctx.lineWidth = 2;
            roundRect(ctx, (WIDTH - pillW) / 2, y, pillW, 56, 28);
            ctx.stroke();
            ctx.fillStyle = muted;
            ctx.fillText(data.status, WIDTH / 2, y + 37);
        }
        y += 56 + 54;

        if (draw) {
            ctx.strokeStyle = hairline;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(PAD, y);
            ctx.lineTo(WIDTH - PAD, y);
            ctx.stroke();
        }
        y += 20;

        for (var r = 0; r < data.rows.length; r++) {
            // Khoang ho giua cac muc phai ro hon khoang giua nhan va gia tri,
            // neu khong ca cot doc lien mot mach, khong tach duoc tung y.
            y += r === 0 ? 30 : 56;
            text(String(data.rows[r][0]).toUpperCase(), body, 25, '600', muted, '5px', 32);
            y += 2;
            text(data.rows[r][1], body, 40, '500', ink, '0px', 50);
        }

        y += 46;

        if (draw) {
            ctx.strokeStyle = hairline;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(PAD, y);
            ctx.lineTo(WIDTH - PAD, y);
            ctx.stroke();
        }
        y += 22;

        text(data.pendingNote || data.footer, body, 26, '400', muted, '0px', 36);

        return y + 72;
    }

    function render() {
        return loadLogo().then(function (logo) {
            var measure = document.createElement('canvas').getContext('2d');
            var height = paint(measure, logo, false);

            var canvas = document.createElement('canvas');
            canvas.width = WIDTH;
            canvas.height = Math.round(height);

            paint(canvas.getContext('2d'), logo, true);

            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    blob ? resolve(blob) : reject(new Error('toBlob rong'));
                }, 'image/png');
            });
        });
    }

    function save(blob) {
        var file = null;

        try {
            file = new File([blob], data.filename, { type: 'image/png' });
        } catch (e) {
            // Trinh duyet cu khong co constructor File, di tiep bang duong tai ve.
        }

        if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
            return navigator.share({ files: [file], title: data.title }).catch(function (err) {
                // Khach bam huy trong bang chia se thi khong phai loi.
                if (err && (err.name === 'AbortError' || err.name === 'NotAllowedError')) {
                    return;
                }
                download(blob);
            });
        }

        download(blob);
        return Promise.resolve();
    }

    function download(blob) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = url;
        link.download = data.filename;
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        link.remove();

        setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    }

    button.addEventListener('click', function () {
        var original = button.textContent;

        button.disabled = true;
        button.textContent = data.labels.saving;

        if (errorBox) {
            errorBox.hidden = true;
        }

        // Cho font cua quan tai xong, neu khong canvas se ve bang font he thong.
        var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();

        ready.then(render).then(save).catch(function () {
            if (errorBox) {
                errorBox.textContent = data.labels.failed;
                errorBox.hidden = false;
            }
        }).then(function () {
            button.disabled = false;
            button.textContent = original;
        });
    });
})();
