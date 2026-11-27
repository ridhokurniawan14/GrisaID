<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Template Excel impor wali kelas: kolom sama dengan export Rombongan Belajar Dapodik + lembar petunjuk. */
final class HomeroomTemplate
{
    private const HEADERS = ['Nama Rombel', 'Tingkat', 'Jurusan', 'Guru/Wali Kelas', 'Nama Ruang', 'Kurikulum', 'Jenis'];

    private const WIDTHS = [20, 12, 40, 32, 12, 44, 10];

    private const SAMPLE = [
        ['X AKL', 'Kelas 10', 'Akuntansi dan Keuangan Lembaga', 'Nama Guru Wali Kelas', 'R1', '', 'Kelas'],
        ['XI KULINER 1', 'Kelas 11', 'Kuliner', 'Nama Guru Lain', 'R2', '', 'Kelas'],
    ];

    public static function download(): StreamedResponse
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Rombel');

        foreach (self::HEADERS as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValueExplicit("{$col}1", $h, DataType::TYPE_STRING);
            $sheet->getColumnDimension($col)->setWidth(self::WIDTHS[$i]);
        }
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->freezePane('A2');

        foreach (self::SAMPLE as $r => $row) {
            foreach ($row as $i => $v) {
                $sheet->setCellValueExplicit(chr(65 + $i).($r + 2), $v, DataType::TYPE_STRING);
            }
        }

        $guide = $book->createSheet();
        $guide->setTitle('Petunjuk');
        $guide->getColumnDimension('A')->setWidth(24);
        $guide->getColumnDimension('B')->setWidth(95);

        $rows = [
            ['PETUNJUK IMPOR WALI KELAS', ''],
            ['', ''],
            ['Dua baris contoh', 'Hapus dua baris contoh di sheet "Rombel", lalu isi data kelas Anda. Baris judul (baris 1) jangan diubah.'],
            ['Nama Rombel', 'WAJIB. Harus sama persis dengan nama kelas di GrisaID pada tahun ajaran yang dipilih (mis. X KULINER 1). Kelas yang belum ada dilaporkan, tidak dibuat otomatis.'],
            ['Guru/Wali Kelas', 'WAJIB. Nama guru sesuai data guru di GrisaID (tanpa gelar, huruf besar/kecil bebas). Nama yang tidak ada atau cocok ke lebih dari satu guru dilaporkan, tidak ditebak.'],
            ['Tingkat, Jurusan, Nama Ruang, Kurikulum, Jenis', 'Opsional, hanya untuk kecocokan dengan format Dapodik. Diabaikan saat impor.'],
            ['Aturan', 'Satu kelas satu wali kelas. Satu guru hanya boleh menjadi wali satu kelas dalam satu tahun ajaran.'],
            ['Dari Dapodik', 'File "Rombongan Belajar" hasil unduhan Dapodik bisa dipakai langsung tanpa template ini.'],
            ['Saran', 'Jalankan dengan "Simpan ke database" mati (simulasi) dulu untuk melihat hasilnya. Sheet Petunjuk ini otomatis diabaikan saat impor.'],
        ];
        foreach ($rows as $i => [$a, $b]) {
            $guide->setCellValueExplicit('A'.($i + 1), $a, DataType::TYPE_STRING);
            $guide->setCellValueExplicit('B'.($i + 1), $b, DataType::TYPE_STRING);
            $guide->getStyle('B'.($i + 1))->getAlignment()->setWrapText(true);
        }
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $guide->getStyle('A3:A9')->getFont()->setBold(true);
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
        }, 'template-impor-wali-kelas.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
