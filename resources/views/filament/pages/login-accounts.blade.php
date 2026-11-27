<x-filament-panels::page>
    @php
        $label = 'font-size:.75rem;color:var(--gray-500);margin-bottom:.125rem';
        $val = 'font-weight:600;font-size:1.25rem';
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem">
        <x-filament::section :compact="true">
            <x-slot name="heading">Guru</x-slot>
            <div style="{{ $label }}">Punya akun / guru aktif</div>
            <div style="{{ $val }}">{{ $teachers['with'] }} / {{ $teachers['total'] }}</div>
        </x-filament::section>
        <x-filament::section :compact="true">
            <x-slot name="heading">Siswa</x-slot>
            <div style="{{ $label }}">Punya akun / siswa aktif</div>
            <div style="{{ $val }}">{{ number_format($students['with'], 0, ',', '.') }} / {{ number_format($students['total'], 0, ',', '.') }}</div>
        </x-filament::section>
    </div>
    <x-filament::section :compact="true">
        <x-slot name="heading">Cara kerja</x-slot>
        <ul style="margin:0;padding-left:1.25rem;font-size:.875rem;color:var(--gray-500);display:flex;flex-direction:column;gap:.375rem">
            <li>Guru masuk dengan <b>email</b>, siswa dengan <b>NISN</b>. Password awal = <b>tanggal lahir (ddmmyyyy)</b>, contoh 17082008.</li>
            <li>Saat masuk pertama, mereka <b>wajib mengganti password</b>.</li>
            <li>Siswa tanpa NISN lengkap atau tanggal lahir dilewati dan dilaporkan; lengkapi datanya lalu jalankan lagi (aman diulang).</li>
            <li>Lupa password: gunakan tombol <b>Reset password</b>. Semua sesi aplikasi akun itu ikut dicabut.</li>
        </ul>
    </x-filament::section>
    <x-filament-actions::modals />
</x-filament-panels::page>
