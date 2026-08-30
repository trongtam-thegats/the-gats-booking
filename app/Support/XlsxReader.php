<?php

namespace App\Support;

use Generator;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Doc tep .xlsx bang dung nhung gi PHP co san (ZipArchive + SimpleXML).
 *
 * Co tinh khong dung thu vien ngoai: ca he thong nay khong co buoc build va
 * hosting chi chay PHP tran, them mot goi lon chi de doc vai tep xuat tu POS
 * la khong dang. Doc du dung: chuoi, so, va ngay thang kieu so cua Excel.
 *
 * Chi doc bang tinh dau tien, va doc theo tung dong nen tep vai nghin dong
 * cung khong an het bo nho.
 */
class XlsxReader
{
    /** Moc ngay cua Excel. Excel coi 1900 la nam nhuan nen so 60 bi lech mot ngay. */
    protected const MOC_EXCEL = -2209161600; // 1899-12-30 00:00:00 UTC

    /** @var array<int, string> */
    protected array $chuoiChung = [];

    public function __construct(protected string $duongDan)
    {
        if (! is_file($duongDan)) {
            throw new RuntimeException('Khong thay tep: '.$duongDan);
        }
    }

    /**
     * Doc tung dong cua bang tinh dau tien.
     *
     * Moi dong tra ve la mang theo chi so cot (0 = A), da dien day du cac o
     * trong o giua de vi tri cot luon dung.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    public function rows(): Generator
    {
        $zip = new ZipArchive;

        if ($zip->open($this->duongDan) !== true) {
            throw new RuntimeException('Khong mo duoc tep xlsx: '.$this->duongDan);
        }

        $this->docChuoiChung($zip);

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheet === false) {
            $zip->close();

            throw new RuntimeException('Tep xlsx khong co bang tinh nao.');
        }

        $zip->close();

        $xml = new SimpleXMLElement($sheet);

        foreach ($xml->sheetData->row as $row) {
            yield $this->doiDong($row);
        }
    }

    /**
     * Doc toan bo cac dong, kem viec tim dong tieu de.
     *
     * @param  callable(array<int, string|float|null>): bool  $laTieuDe
     * @return array{0: array<int, string>, 1: array<int, array<string, string|float|null>>}
     */
    public function table(callable $laTieuDe): array
    {
        $tieuDe = null;
        $dong = [];

        foreach ($this->rows() as $row) {
            if ($tieuDe === null) {
                if ($laTieuDe($row)) {
                    $tieuDe = array_map(fn ($o) => $this->gonChu((string) $o), $row);
                }

                continue;
            }

            $ban = [];

            foreach ($tieuDe as $i => $ten) {
                if ($ten !== '') {
                    $ban[$ten] = $row[$i] ?? null;
                }
            }

            $dong[] = $ban;
        }

        return [$tieuDe ?? [], $dong];
    }

    /** Ten cot trong tep xuat hay xuong dong giua chung; gom lai mot dong. */
    public function gonChu(string $chu): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $chu));
    }

    /**
     * So ngay kieu Excel doi ve chuoi ngay gio.
     * O da la chuoi san thi tra ve nguyen van.
     */
    public function ngay(string|float|int|null $o): ?string
    {
        if ($o === null || $o === '') {
            return null;
        }

        if (is_string($o) && ! is_numeric($o)) {
            return $o;
        }

        $giay = self::MOC_EXCEL + (int) round(((float) $o) * 86400);

        return gmdate('Y-m-d H:i:s', $giay);
    }

    /**
     * @return array<int, string|float|null>
     */
    protected function doiDong(SimpleXMLElement $row): array
    {
        $ket = [];

        foreach ($row->c as $c) {
            $viTri = $this->cotTuDiaChi((string) $c['r']);
            $kieu = (string) $c['t'];

            if ($kieu === 'inlineStr') {
                $ket[$viTri] = trim((string) $c->is->t);

                continue;
            }

            $gt = (string) $c->v;

            if ($gt === '') {
                $ket[$viTri] = null;

                continue;
            }

            $ket[$viTri] = match ($kieu) {
                's' => $this->chuoiChung[(int) $gt] ?? '',
                'str', 'e' => $gt,
                'b' => $gt === '1' ? '1' : '0',
                default => (float) $gt,
            };
        }

        // Dien cac o trong o giua de chi so cot khong bi truot.
        if ($ket) {
            $ket += array_fill(0, max(array_keys($ket)) + 1, null);
            ksort($ket);
        }

        return $ket;
    }

    /** "BC12" -> 54 (chi so cot, A = 0). */
    protected function cotTuDiaChi(string $diaChi): int
    {
        $chu = rtrim($diaChi, '0123456789');
        $so = 0;

        for ($i = 0; $i < strlen($chu); $i++) {
            $so = $so * 26 + (ord($chu[$i]) - 64);
        }

        return max(0, $so - 1);
    }

    protected function docChuoiChung(ZipArchive $zip): void
    {
        $noiDung = $zip->getFromName('xl/sharedStrings.xml');

        if ($noiDung === false) {
            return;
        }

        $xml = new SimpleXMLElement($noiDung);

        foreach ($xml->si as $si) {
            // Mot o co the gom nhieu doan chu dinh dang khac nhau (<r><t>).
            $this->chuoiChung[] = $si->t->count()
                ? trim((string) $si->t)
                : trim(implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r))));
        }
    }
}
