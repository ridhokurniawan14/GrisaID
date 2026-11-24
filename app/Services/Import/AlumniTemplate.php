<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Template Excel impor alumni: satu sheet per tahun lulus + lembar petunjuk. */
final class AlumniTemplate
{
    private const HEADERS = ['Nama', 'L/P', 'NIK', 'NISN', 'Tanggal Lahir', 'Nama Ibu', 'Rombel Terakhir'];

    private const WIDTHS = [34, 7, 20, 14, 16, 28, 18];

    public static function download(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $year = (string) date('Y');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($year);
        self::fillHeader($sheet);

        // Kolom NIK & NISN berformat teks agar angka nol di depan tidak hilang; tanggal berformat dd/mm/yyyy.
        $sheet->getStyle('C2:D2000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('E2:E2000')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('B2:B2000')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Petunjuk');
        $guide->getColumnDimension('A')->setWidth(24);
        $guide->getColumnDimension('B')->setWidth(90);

        $rows = [
            ['PETUNJUK IMPOR ALUMNI', ''],
            ['', ''],
            ['Satu sheet = satu tahun lulus', 'Beri nama sheet dengan tahun lulus, mis. 2024. Untuk tahun lain, salin sheet ini lalu ganti nama sheet-nya.'],
            ['Nama', 'Wajib.'],
            ['L/P', 'Wajib. Isi L atau P.'],
            ['NIK', 'Opsional, 16 digit. Disimpan terenkripsi.'],
            ['NISN', 'Wajib, 10 digit (nol di depan tetap ditulis). Dipakai untuk mencocokkan siswa; yang sudah ada tidak diubah statusnya.'],
            ['Tanggal Lahir', 'Opsional. Format 25/12/2007 atau 2007-12-25.'],
            ['Nama Ibu', 'Opsional.'],
            ['Rombel Terakhir', 'Boleh dikosongkan atau diisi apa saja; kolom ini diabaikan (alumni dibuat tanpa penempatan kelas).'],
            ['', ''],
            ['Contoh baris', 'BUDI SANTOSO | L | 3510161234560001 | 0012345678 | 17/08/2005 | SITI AMINAH | (dikosongkan)'],
            ['Catatan', 'Jalankan "Simpan ke database" mati (simulasi) dulu untuk melihat hasilnya sebelum disimpan. Lembar Petunjuk ini otomatis diabaikan saat impor.'],
        ];

        foreach ($rows as $i => [$a, $b]) {
            $r = $i + 1;
            $guide->setCellValueExplicit("A{$r}", $a, DataType::TYPE_STRING);
            $guide->setCellValueExplicit("B{$r}", $b, DataType::TYPE_STRING);
            $guide->getStyle("B{$r}")->getAlignment()->setWrapText(true);
        }

        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $guide->getStyle('A3:A13')->getFont()->setBold(true);
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template-impor-alumni.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function fillHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        foreach (self::HEADERS as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValueExplicit("{$col}1", $label, DataType::TYPE_STRING);
            $sheet->getColumnDimension($col)->setWidth(self::WIDTHS[$i]);
        }

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');
    }
}
