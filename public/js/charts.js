/*
 * Bieu do cho trang bao cao.
 *
 * Viet bang SVG thuan, khong dung thu vien ngoai: hosting cua chuoi chi chay
 * PHP, khong co buoc build, va trang quan tri phai mo duoc ca khi mang yeu.
 *
 * Moi bieu do doc du lieu tu the <script type="application/json"> nam ngay
 * trong figure cua no, nen du lieu va hinh ve luon di cung nhau.
 *
 * Quy uoc ve: net mong, luoi mo mot bac so voi nen, khong vien quanh cot ma
 * dung khe ho 2px, chi ghi nhan o nhung diem dang chu y chu khong ghi so len
 * moi cot. Moi bieu do deu co bang so liệu kem theo de doc duoc khi khong
 * phan biet duoc mau.
 */
(function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';

    const palette = {
        grid: '#2f2a25',
        axis: '#6b6259',
        text: '#efeae3',
        muted: '#9b9188',
        surface: '#1a1816',
        // Bac thang mau xanh: dung cho cac buoc co thu tu (phieu quy trinh)
        // va cho cot khong duoc nhan manh.
        ramp: ['#184f95', '#256abf', '#2a78d6', '#3987e5'],
        series: '#3987e5',
        // Mau trang thai: chi dung khi mau that su mang nghia tot/xau.
        good: '#0ca30c',
        warning: '#fab219',
        critical: '#d03b3b',
        // Mau nhan dang cho nguon dat ban.
        categorical: ['#3987e5', '#d95926', '#199e70'],
    };

    function el(name, attrs, parent) {
        const node = document.createElementNS(NS, name);
        for (const key in attrs) {
            if (attrs[key] !== null && attrs[key] !== undefined) {
                node.setAttribute(key, attrs[key]);
            }
        }
        if (parent) parent.appendChild(node);
        return node;
    }

    function niceMax(value) {
        if (value <= 0) return 1;
        const magnitude = Math.pow(10, Math.floor(Math.log10(value)));
        const steps = [1, 2, 2.5, 5, 10];
        for (const step of steps) {
            const candidate = step * magnitude;
            if (candidate >= value) return candidate;
        }
        return 10 * magnitude;
    }

    // Rut gon con so dai cho truc va nhan tren cot. Tien Viet len den hang ti
    // nen de nguyen thi truc chi toan chu so, khong ai doc noi.
    function gonSo(value, kieu) {
        const n = Number(value) || 0;

        if (kieu !== 'tien' && Math.abs(n) < 10000) return String(n);

        const abs = Math.abs(n);
        if (abs >= 1e9) return trimSo(n / 1e9) + ' tỉ';
        if (abs >= 1e6) return trimSo(n / 1e6) + ' tr';
        if (abs >= 1e3) return trimSo(n / 1e3) + 'k';

        return String(n);
    }

    function trimSo(n) {
        return (Math.round(n * 10) / 10).toString().replace('.', ',');
    }

    function ticks(max, count) {
        const out = [];
        for (let i = 0; i <= count; i++) out.push(Math.round((max / count) * i));
        return [...new Set(out)];
    }

    /* ---------- Lop chu giai thich khi ro chuot ---------- */

    function makeTooltip(host) {
        const tip = document.createElement('div');
        tip.className = 'viz-tip';
        tip.hidden = true;
        host.appendChild(tip);

        return {
            show(html, x, y) {
                tip.innerHTML = html;
                tip.hidden = false;
                const box = host.getBoundingClientRect();
                const own = tip.getBoundingClientRect();
                let left = x - own.width / 2;
                left = Math.max(4, Math.min(left, box.width - own.width - 4));
                tip.style.left = left + 'px';
                tip.style.top = Math.max(4, y - own.height - 12) + 'px';
            },
            hide() {
                tip.hidden = true;
            },
        };
    }

    /* ---------- Bieu do duong nhieu tuyen: so hai xu huong voi nhau ---------- */

    /**
     * Ve nhieu duong tren cung mot truc.
     *
     * Khac bieu do vung o cho: dung de SO SANH hai day so, khong phai de nhin
     * mot day so lon nho ra sao. Vi vay khong to nen duoi duong - to nen se
     * lam duong nam duoi bi che khuat.
     *
     * Tuong tac: ra vao bang chuot thi co duong doc va mot cham tren moi tuyen,
     * chu giai bam duoc de tat bot tuyen cho de nhin.
     */
    function drawLines(host, config) {
        const rows = config.rows;
        if (!rows.length) return empty(host);

        const width = host.clientWidth || 640;
        const height = config.height || 240;
        const pad = { top: 16, right: 12, bottom: 26, left: 34 };
        const plotW = Math.max(10, width - pad.left - pad.right);
        const plotH = height - pad.top - pad.bottom;

        // Tuyen nao dang bat. Tat het thi bat lai tuyen vua bam, khong de trong.
        const dangBat = new Set(config.keys.map((k) => k.key));

        const svg = el('svg', {
            viewBox: `0 0 ${width} ${height}`, width: '100%', height,
            role: 'img', 'aria-label': config.label,
        }, host);

        const lopLuoi = el('g', {}, svg);
        const lopDuong = el('g', {}, svg);
        const tip = makeTooltip(host);

        const stepX = rows.length > 1 ? plotW / (rows.length - 1) : 0;
        const xAt = (i) => pad.left + (rows.length > 1 ? i * stepX : plotW / 2);

        let max = 1;
        let yAt = (v) => pad.top + plotH;

        // Cac cham theo con tro, moi tuyen mot cham.
        const cham = {};
        const marker = el('line', {
            x1: pad.left, x2: pad.left, y1: pad.top, y2: pad.top + plotH,
            stroke: palette.axis, 'stroke-width': 1, visibility: 'hidden',
        }, svg);

        function veLai() {
            lopLuoi.textContent = '';
            lopDuong.textContent = '';

            const bat = config.keys.filter((k) => dangBat.has(k.key));
            const dinh = Math.max(1, ...rows.flatMap((r) => bat.map((k) => Number(r[k.key]) || 0)));

            max = niceMax(dinh);
            yAt = (v) => pad.top + plotH - (v / max) * plotH;

            ticks(max, 3).forEach((value) => {
                const y = yAt(value);
                el('line', {
                    x1: pad.left, x2: width - pad.right, y1: y, y2: y,
                    stroke: palette.grid, 'stroke-width': 1,
                }, lopLuoi);
                el('text', { x: 4, y: y + 4, fill: palette.muted, 'font-size': 11 }, lopLuoi)
                    .textContent = gonSo(value, config.unit);
            });

            labelXAxis(lopLuoi, rows, xAt, pad.top + plotH + 16);

            bat.forEach((key) => {
                const mau = palette[key.color] || key.color || palette.series;
                const d = rows
                    .map((r, i) => `${i === 0 ? 'M' : 'L'}${xAt(i)},${yAt(Number(r[key.key]) || 0)}`)
                    .join(' ');

                el('path', {
                    d, fill: 'none', stroke: mau, 'stroke-width': 2,
                    'stroke-linejoin': 'round', 'stroke-linecap': 'round',
                }, lopDuong);

                // Moc tron o tung thang, giup dem duoc so diem khi duong phang.
                if (rows.length <= 20) {
                    rows.forEach((r, i) => {
                        el('circle', {
                            cx: xAt(i), cy: yAt(Number(r[key.key]) || 0), r: 2.5,
                            fill: palette.surface, stroke: mau, 'stroke-width': 1.5,
                        }, lopDuong);
                    });
                }
            });

            // Cham theo con tro phai ve lai sau khi doi tuyen dang bat.
            config.keys.forEach((key) => {
                if (cham[key.key]) cham[key.key].remove();

                cham[key.key] = el('circle', {
                    cx: pad.left, cy: pad.top + plotH, r: 4.5,
                    fill: palette[key.color] || key.color || palette.series,
                    stroke: palette.surface, 'stroke-width': 2, visibility: 'hidden',
                }, svg);
            });
        }

        veLai();

        function gan(event) {
            const box = svg.getBoundingClientRect();
            const x = (event.clientX - box.left) * (width / box.width);
            const i = rows.length > 1 ? Math.round((x - pad.left) / stepX) : 0;

            return Math.max(0, Math.min(rows.length - 1, i));
        }

        svg.addEventListener('pointermove', (event) => {
            const i = gan(event);
            const x = xAt(i);

            marker.setAttribute('x1', x);
            marker.setAttribute('x2', x);
            marker.setAttribute('visibility', 'visible');
            marker.setAttribute('opacity', 0.5);

            let cao = pad.top + plotH;

            config.keys.forEach((key) => {
                const c = cham[key.key];

                if (! dangBat.has(key.key)) {
                    c.setAttribute('visibility', 'hidden');

                    return;
                }

                const y = yAt(Number(rows[i][key.key]) || 0);
                c.setAttribute('cx', x);
                c.setAttribute('cy', y);
                c.setAttribute('visibility', 'visible');
                cao = Math.min(cao, y);
            });

            const box = svg.getBoundingClientRect();
            tip.show(config.tip(rows[i]), x * (box.width / width), cao * (box.height / height));
        });

        svg.addEventListener('pointerleave', () => {
            marker.setAttribute('visibility', 'hidden');
            Object.values(cham).forEach((c) => c.setAttribute('visibility', 'hidden'));
            tip.hide();
        });

        veChuGiai(host, config, dangBat, veLai);
    }

    /** Chu giai bam duoc de tat/bat tung tuyen. */
    function veChuGiai(host, config, dangBat, veLai) {
        const box = document.createElement('div');
        box.className = 'viz-legend';

        config.keys.forEach((key) => {
            const nut = document.createElement('button');
            nut.type = 'button';
            nut.className = 'viz-legend-item';
            nut.setAttribute('aria-pressed', 'true');
            nut.innerHTML = '<i style="background:'
                + (palette[key.color] || key.color || palette.series) + '"></i><span></span>';
            nut.querySelector('span').textContent = key.label;

            nut.addEventListener('click', () => {
                // Khong cho tat tuyen cuoi cung: bieu do trong khong noi len gi.
                if (dangBat.has(key.key) && dangBat.size === 1) return;

                dangBat.has(key.key) ? dangBat.delete(key.key) : dangBat.add(key.key);
                nut.setAttribute('aria-pressed', dangBat.has(key.key) ? 'true' : 'false');
                veLai();
            });

            box.appendChild(nut);
        });

        host.appendChild(box);
    }

    /* ---------- Bieu do duong / vung: xu huong theo thoi gian ---------- */

    function drawArea(host, config) {
        const rows = config.rows;
        if (!rows.length) return empty(host);

        const width = host.clientWidth || 640;
        const height = config.height || 220;
        const pad = { top: 16, right: 12, bottom: 26, left: 34 };
        const plotW = Math.max(10, width - pad.left - pad.right);
        const plotH = height - pad.top - pad.bottom;

        const svg = el('svg', {
            viewBox: `0 0 ${width} ${height}`, width: '100%', height,
            role: 'img', 'aria-label': config.label,
        }, host);

        const max = niceMax(Math.max(...rows.map((r) => r.value)));
        const stepX = rows.length > 1 ? plotW / (rows.length - 1) : 0;
        const xAt = (i) => pad.left + (rows.length > 1 ? i * stepX : plotW / 2);
        const yAt = (v) => pad.top + plotH - (v / max) * plotH;

        ticks(max, 3).forEach((value) => {
            const y = yAt(value);
            el('line', { x1: pad.left, x2: width - pad.right, y1: y, y2: y, stroke: palette.grid, 'stroke-width': 1 }, svg);
            el('text', { x: 4, y: y + 4, fill: palette.muted, 'font-size': 11 }, svg).textContent = gonSo(value, config.unit);
        });

        // Vung to nhat duoi duong, dam o tren nhat dan xuong duoi.
        const gradientId = 'grad-' + Math.random().toString(36).slice(2, 8);
        const defs = el('defs', {}, svg);
        const grad = el('linearGradient', { id: gradientId, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
        el('stop', { offset: '0%', 'stop-color': palette.series, 'stop-opacity': 0.32 }, grad);
        el('stop', { offset: '100%', 'stop-color': palette.series, 'stop-opacity': 0.02 }, grad);

        const line = rows.map((r, i) => `${i === 0 ? 'M' : 'L'}${xAt(i)},${yAt(r.value)}`).join(' ');
        el('path', {
            d: `${line} L${xAt(rows.length - 1)},${pad.top + plotH} L${xAt(0)},${pad.top + plotH} Z`,
            fill: `url(#${gradientId})`,
        }, svg);
        el('path', { d: line, fill: 'none', stroke: palette.series, 'stroke-width': 2, 'stroke-linejoin': 'round' }, svg);

        // Chi ghi nhan o diem cao nhat, khong ghi so len moi diem.
        const peak = rows.reduce((best, r, i) => (r.value > rows[best].value ? i : best), 0);
        if (rows[peak].value > 0) {
            el('circle', { cx: xAt(peak), cy: yAt(rows[peak].value), r: 4, fill: palette.series, stroke: palette.surface, 'stroke-width': 2 }, svg);
            const anchor = peak > rows.length - 3 ? 'end' : (peak < 2 ? 'start' : 'middle');
            el('text', {
                x: xAt(peak), y: Math.max(12, yAt(rows[peak].value) - 10),
                fill: palette.text, 'font-size': 11, 'font-weight': 600, 'text-anchor': anchor,
            }, svg).textContent = gonSo(rows[peak].value, config.unit);
        }

        labelXAxis(svg, rows, xAt, pad.top + plotH + 16);

        // Duong doc va cham theo con tro. Dat san trong khung va an bang
        // visibility: neu de o toa do 0,0 thi khung ve bi keo ra ngoai le.
        const marker = el('line', {
            x1: pad.left, x2: pad.left, y1: pad.top, y2: pad.top + plotH,
            stroke: palette.axis, 'stroke-width': 1, visibility: 'hidden',
        }, svg);
        const dot = el('circle', {
            cx: pad.left, cy: pad.top + plotH, r: 4,
            fill: palette.series, stroke: palette.surface, 'stroke-width': 2, visibility: 'hidden',
        }, svg);
        const tip = makeTooltip(host);

        function nearest(event) {
            const box = svg.getBoundingClientRect();
            const x = (event.clientX - box.left) * (width / box.width);
            let index = rows.length > 1 ? Math.round((x - pad.left) / stepX) : 0;
            return Math.max(0, Math.min(rows.length - 1, index));
        }

        svg.addEventListener('pointermove', (event) => {
            const i = nearest(event);
            const x = xAt(i);
            const y = yAt(rows[i].value);
            marker.setAttribute('x1', x); marker.setAttribute('x2', x);
            marker.setAttribute('visibility', 'visible'); marker.setAttribute('opacity', 0.5);
            dot.setAttribute('cx', x); dot.setAttribute('cy', y);
            dot.setAttribute('visibility', 'visible');
            const box = svg.getBoundingClientRect();
            tip.show(config.tip(rows[i]), x * (box.width / width), y * (box.height / height));
        });

        svg.addEventListener('pointerleave', () => {
            marker.setAttribute('visibility', 'hidden');
            dot.setAttribute('visibility', 'hidden');
            tip.hide();
        });
    }

    /* ---------- Bieu do cot: so sanh do lon ---------- */

    function drawColumns(host, config) {
        const rows = config.rows;
        if (!rows.length) return empty(host);

        const width = host.clientWidth || 640;
        const height = config.height || 200;
        const pad = { top: 16, right: 8, bottom: 26, left: 30 };
        const plotW = Math.max(10, width - pad.left - pad.right);
        const plotH = height - pad.top - pad.bottom;

        const svg = el('svg', { viewBox: `0 0 ${width} ${height}`, width: '100%', height, role: 'img', 'aria-label': config.label }, host);

        const values = rows.map((r) => r.value);
        const max = niceMax(Math.max(...values));
        const peak = Math.max(...values);
        const slot = plotW / rows.length;
        const barW = Math.max(4, Math.min(38, slot - 6));
        const tip = makeTooltip(host);
        // Qua nhieu cot ma cot nao cung dung Tab duoc thi ban phim khong the
        // di qua noi trang; luc do bang so lieu la duong doc chinh.
        const focusable = rows.length <= 24;

        ticks(max, 2).forEach((value) => {
            const y = pad.top + plotH - (value / max) * plotH;
            el('line', { x1: pad.left, x2: width - pad.right, y1: y, y2: y, stroke: palette.grid, 'stroke-width': 1 }, svg);
            el('text', { x: 2, y: y + 4, fill: palette.muted, 'font-size': 11 }, svg).textContent = gonSo(value, config.unit);
        });

        // Cot cao nhat duoc lam noi len, con lai cung mot mau diu hon.
        // Khong to dam dan theo gia tri: chieu cao cot da noi len dieu do roi,
        // to them mot lan nua chi lam ton mat mau ma khong them thong tin.
        const peakIndex = rows.reduce((best, r, i) => (r.value > rows[best].value ? i : best), 0);

        rows.forEach((row, i) => {
            const x = pad.left + slot * i + (slot - barW) / 2;
            const h = peak > 0 ? (row.value / max) * plotH : 0;
            const y = pad.top + plotH - h;
            const isPeak = i === peakIndex && row.value > 0;

            const rect = el('rect', {
                x, y, width: barW, height: Math.max(row.value > 0 ? 2 : 0, h),
                rx: 4, fill: isPeak ? palette.series : palette.ramp[1],
            }, svg);

            // Ghi so ngay tren cot cao nhat, cac cot con lai de tooltip va bang so.
            if (isPeak) {
                el('text', {
                    x: x + barW / 2, y: Math.max(11, y - 6),
                    fill: palette.text, 'font-size': 11, 'font-weight': 600, 'text-anchor': 'middle',
                }, svg).textContent = gonSo(row.value, config.unit);
            }

            // Vung bam trong suot phu ca o, de ngon tay khong phai nham dung cot.
            const hit = el('rect', {
                x: pad.left + slot * i, y: pad.top, width: slot, height: plotH,
                fill: 'transparent', role: 'listitem',
                tabindex: focusable ? 0 : null,
                'aria-label': `${row.label}: ${row.value}`,
            }, svg);

            const reveal = () => {
                const box = svg.getBoundingClientRect();
                tip.show(config.tip(row), (x + barW / 2) * (box.width / width), y * (box.height / height));
                rect.setAttribute('opacity', 0.82);
            };
            const clear = () => { tip.hide(); rect.removeAttribute('opacity'); };

            hit.addEventListener('pointerenter', reveal);
            hit.addEventListener('focus', reveal);
            hit.addEventListener('pointerleave', clear);
            hit.addEventListener('blur', clear);
        });

        labelXAxis(svg, rows, (i) => pad.left + slot * i + slot / 2, pad.top + plotH + 16);
    }

    /* ---------- Cot chong: ket qua cua tung ngay ---------- */

    function drawStacked(host, config) {
        const rows = config.rows;
        if (!rows.length) return empty(host);

        const width = host.clientWidth || 640;
        const height = config.height || 200;
        const pad = { top: 16, right: 8, bottom: 26, left: 30 };
        const plotW = Math.max(10, width - pad.left - pad.right);
        const plotH = height - pad.top - pad.bottom;

        const svg = el('svg', { viewBox: `0 0 ${width} ${height}`, width: '100%', height, role: 'img', 'aria-label': config.label }, host);

        const totals = rows.map((r) => config.keys.reduce((sum, k) => sum + r[k.key], 0));
        const max = niceMax(Math.max(...totals));
        const slot = plotW / rows.length;
        const barW = Math.max(4, Math.min(30, slot - 5));
        const tip = makeTooltip(host);
        const focusable = rows.length <= 24;

        ticks(max, 2).forEach((value) => {
            const y = pad.top + plotH - (value / max) * plotH;
            el('line', { x1: pad.left, x2: width - pad.right, y1: y, y2: y, stroke: palette.grid, 'stroke-width': 1 }, svg);
            el('text', { x: 2, y: y + 4, fill: palette.muted, 'font-size': 11 }, svg).textContent = gonSo(value, config.unit);
        });

        rows.forEach((row, i) => {
            const x = pad.left + slot * i + (slot - barW) / 2;
            let cursor = pad.top + plotH;

            const group = el('g', {}, svg);

            config.keys.forEach((key) => {
                const value = row[key.key];
                if (value <= 0) return;
                const h = (value / max) * plotH;
                // Khe ho 2px giua cac doan thay cho duong vien.
                cursor -= h;
                el('rect', { x, y: cursor, width: barW, height: Math.max(2, h - 2), rx: 2, fill: key.color }, group);
            });

            // Vung bam trong suot phu ca o, rong hon cot that.
            const hit = el('rect', {
                x: pad.left + slot * i, y: pad.top, width: slot, height: plotH,
                fill: 'transparent', role: 'listitem',
                tabindex: focusable ? 0 : null,
                'aria-label': config.aria(row),
            }, svg);

            const reveal = () => {
                const box = svg.getBoundingClientRect();
                tip.show(config.tip(row), (x + barW / 2) * (box.width / width), cursor * (box.height / height));
                group.setAttribute('opacity', 0.82);
            };
            const clear = () => { tip.hide(); group.removeAttribute('opacity'); };

            hit.addEventListener('pointerenter', reveal);
            hit.addEventListener('focus', reveal);
            hit.addEventListener('pointerleave', clear);
            hit.addEventListener('blur', clear);
        });

        labelXAxis(svg, rows, (i) => pad.left + slot * i + slot / 2, pad.top + plotH + 16);
    }

    /* ---------- Thanh ngang: phieu quy trinh va co cau nguon ---------- */

    function drawRows(host, config) {
        const rows = config.rows;
        if (!rows.length) return empty(host);

        const tip = makeTooltip(host);
        const list = document.createElement('div');
        list.className = 'viz-rows';
        host.appendChild(list);

        const max = Math.max(...rows.map((r) => r.value), 1);

        rows.forEach((row, i) => {
            const item = document.createElement('div');
            item.className = 'viz-row';
            item.tabIndex = 0;

            const head = document.createElement('div');
            head.className = 'viz-row-head';
            head.innerHTML = `<span>${row.label}</span><b>${config.value(row)}</b>`;

            const track = document.createElement('div');
            track.className = 'viz-row-track';
            const fill = document.createElement('span');
            fill.style.width = Math.max(row.value > 0 ? 2 : 0, (row.value / max) * 100) + '%';
            fill.style.background = config.color ? config.color(row, i) : palette.ramp[palette.ramp.length - 1];
            track.appendChild(fill);

            item.append(head, track);

            if (config.tip) {
                const reveal = () => {
                    const box = host.getBoundingClientRect();
                    const own = item.getBoundingClientRect();
                    tip.show(config.tip(row), own.left - box.left + own.width / 2, own.top - box.top + 8);
                };
                item.addEventListener('pointerenter', reveal);
                item.addEventListener('focus', reveal);
                item.addEventListener('pointerleave', () => tip.hide());
                item.addEventListener('blur', () => tip.hide());
            }

            list.appendChild(item);
        });
    }

    /* ---------- Tien ich chung ---------- */

    function labelXAxis(svg, rows, xAt, y) {
        // Nhan day thi bo bot cho khoi chong chu len nhau.
        const every = Math.max(1, Math.ceil(rows.length / 12));
        const last = rows.length - 1;

        rows.forEach((row, i) => {
            if (i % every !== 0 && i !== last) return;

            // Nhan dau va nhan cuoi neo vao trong, neu de canh giua thi chu
            // thò ra ngoai me bieu do.
            const anchor = i === 0 ? 'start' : (i === last ? 'end' : 'middle');

            el('text', {
                x: xAt(i), y, fill: palette.muted, 'font-size': 11, 'text-anchor': anchor,
            }, svg).textContent = row.label;
        });
    }

    function empty(host) {
        const note = document.createElement('p');
        note.className = 'viz-empty';
        note.textContent = 'Chưa có dữ liệu trong khoảng thời gian này.';
        host.appendChild(note);
    }

    /* ---------- Khoi tao ---------- */

    const builders = { area: drawArea, columns: drawColumns, stacked: drawStacked, rows: drawRows };

    function render(figure) {
        const host = figure.querySelector('.viz-plot');
        const source = figure.querySelector('script[type="application/json"]');
        if (!host || !source) return;

        const config = JSON.parse(source.textContent);
        const rows = config.rows || [];
        host.innerHTML = '';

        const shared = {
            rows, label: config.label || '', height: config.height,
            keys: (config.keys || []).map((k) => ({ ...k, color: palette[k.color] || k.color })),
        };

        if (config.type === 'lines') {
            drawLines(host, { ...shared, tip: (r) => tipHtml(config, r) });
        } else if (config.type === 'area') {
            drawArea(host, { ...shared, tip: (r) => tipHtml(config, r) });
        } else if (config.type === 'columns') {
            drawColumns(host, { ...shared, tip: (r) => tipHtml(config, r) });
        } else if (config.type === 'stacked') {
            drawStacked(host, {
                ...shared,
                tip: (r) => tipHtml(config, r),
                aria: (r) => `${r.label}: ` + shared.keys.map((k) => `${k.label} ${r[k.key]}`).join(', '),
            });
        } else if (config.type === 'rows') {
            drawRows(host, {
                ...shared,
                value: (r) => r.display || r.value,
                color: (r, i) => palette[config.colors?.[i]] || config.colors?.[i] || palette.ramp[palette.ramp.length - 1],
                tip: config.fields ? (r) => tipHtml(config, r) : null,
            });
        }
    }

    function tipHtml(config, row) {
        const lines = (config.fields || []).map((field) => {
            const value = row[field.key];
            const swatch = field.color
                ? `<i style="background:${palette[field.color] || field.color}"></i>`
                : '';
            return `<div>${swatch}<span>${field.label}</span><b>${value}${field.unit || ''}</b></div>`;
        });
        return `<strong>${row.tipLabel || row.label}</strong>${lines.join('')}`;
    }

    function renderAll() {
        document.querySelectorAll('.viz-figure').forEach(render);
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderAll();

        // Ve lai khi khung chua doi be rong. Dung ResizeObserver thay vi bat
        // su kien resize cua cua so: bieu do nam trong the dang an se co be rong
        // 0 luc tai trang, va chi ResizeObserver moi biet luc no hien ra.
        if ('ResizeObserver' in window) {
            let lastWidths = new WeakMap();
            const observer = new ResizeObserver((entries) => {
                entries.forEach((entry) => {
                    const figure = entry.target.closest('.viz-figure');
                    const width = Math.round(entry.contentRect.width);
                    if (!figure || width === 0 || lastWidths.get(entry.target) === width) return;
                    lastWidths.set(entry.target, width);
                    if (!figure.classList.contains('is-table')) render(figure);
                });
            });
            document.querySelectorAll('.viz-figure .viz-plot').forEach((plot) => observer.observe(plot));
        } else {
            let timer = null;
            window.addEventListener('resize', () => {
                clearTimeout(timer);
                timer = setTimeout(renderAll, 180);
            });
        }

        // Nut chuyen giua bieu do va bang so lieu.
        // Nut nam trong phan dau the, khong nam trong <figure>, nen phai tim
        // figure trong cung the chu khong dung closest() len tren.
        document.querySelectorAll('[data-viz-toggle]').forEach((button) => {
            const figure = (button.closest('.card') || document).querySelector('.viz-figure');
            if (!figure) return;

            button.addEventListener('click', () => {
                const showTable = figure.classList.toggle('is-table');
                button.textContent = showTable ? 'Xem biểu đồ' : 'Xem bảng số';
                button.setAttribute('aria-pressed', showTable ? 'true' : 'false');
                if (!showTable) render(figure);
            });
        });
    });
})();
