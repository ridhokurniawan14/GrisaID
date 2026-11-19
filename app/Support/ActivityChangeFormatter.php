<?php

namespace App\Support;

/**
 * Mengubah kolom attribute_changes milik Spatie Activitylog v5
 * (['attributes' => nilai baru, 'old' => nilai lama]) menjadi baris yang mudah dibaca.
 */
final class ActivityChangeFormatter
{
    private const LABELS = [
        'nisn' => 'NISN',
        'nis' => 'NIS',
        'full_name' => 'Nama',
        'gender' => 'JK',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tgl lahir',
        'address' => 'Alamat',
        'phone' => 'WA',
        'entry_year' => 'Angkatan',
        'status' => 'Status',
        'status_changed_at' => 'Waktu status',
        'code' => 'Kode',
        'name' => 'Nama',
        'start_date' => 'Mulai',
        'end_date' => 'Selesai',
        'is_active' => 'Aktif',
        'academic_year_id' => 'ID tahun ajaran',
        'major_id' => 'ID jurusan',
        'grade' => 'Tingkat',
        'group_no' => 'No. rombel',
        'relation' => 'Hubungan',
        'occupation' => 'Pekerjaan',
        'whatsapp' => 'WhatsApp',
        'is_primary_contact' => 'Kontak utama',
        'student_id' => 'ID siswa',
        'classroom_id' => 'ID kelas',
        'enrolled_at' => 'Tgl masuk kelas',
        'left_at' => 'Tgl keluar kelas',
        'notes' => 'Catatan',
    ];

    /** @return list<string> */
    public static function lines(mixed $changes): array
    {
        if (is_object($changes) && method_exists($changes, 'toArray')) {
            $changes = $changes->toArray();
        }

        $changes = is_array($changes) ? $changes : [];
        $new = is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [];
        $old = is_array($changes['old'] ?? null) ? $changes['old'] : [];

        $lines = [];

        foreach ($new as $key => $value) {
            $label = self::LABELS[$key] ?? (string) $key;

            $lines[] = array_key_exists($key, $old)
                ? "{$label}: " . self::show($old[$key]) . ' → ' . self::show($value)
                : "{$label}: " . self::show($value);
        }

        foreach (array_diff_key($old, $new) as $key => $value) {
            $lines[] = (self::LABELS[$key] ?? (string) $key) . ': ' . self::show($value) . ' → (dihapus)';
        }

        return $lines;
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '(kosong)',
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_array($value) => mb_strimwidth((string) json_encode($value, JSON_UNESCAPED_UNICODE), 0, 60, '…'),
            default => mb_strimwidth((string) $value, 0, 60, '…'),
        };
    }
}
