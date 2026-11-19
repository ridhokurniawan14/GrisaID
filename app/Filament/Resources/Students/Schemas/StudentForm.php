<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nisn')
                    ->label('NISN')
                    ->maxLength(10)
                    ->regex('/^\d{10}$/')
                    ->validationMessages(['regex' => 'NISN harus 10 digit angka.'])
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? trim($state) : null),
                TextInput::make('nis')
                    ->label('NIS / NIPD')
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? trim($state) : null),
                TextInput::make('full_name')
                    ->label('Nama lengkap')
                    ->required()
                    ->maxLength(255),
                Select::make('gender')
                    ->label('Jenis kelamin')
                    ->options(Gender::options())
                    ->required(),
                TextInput::make('birth_place')
                    ->label('Tempat lahir')
                    ->maxLength(255),
                DatePicker::make('birth_date')
                    ->label('Tanggal lahir')
                    ->maxDate(now()),
                TextInput::make('phone')
                    ->label('No. WhatsApp siswa')
                    ->tel()
                    ->maxLength(20)
                    ->helperText('Format bebas, disimpan sebagai 08xxxxxxxxxx.')
                    ->dehydrateStateUsing(fn(?string $state): ?string => self::normalizePhone($state)),
                TextInput::make('entry_year')
                    ->label('Angkatan (tahun masuk)')
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->required()
                    ->default(fn() => AcademicYear::current()?->startYear()),
                Select::make('status')
                    ->label('Status siswa')
                    ->options(StudentStatus::options())
                    ->default(StudentStatus::Active->value)
                    ->required(),
                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(2)
                    ->columnSpanFull(),
                Repeater::make('guardians')
                    ->label('Orang tua / wali')
                    ->relationship()
                    ->schema([
                        Select::make('relation')
                            ->label('Hubungan')
                            ->options(GuardianRelation::options())
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('name')
                            ->label('Nama')
                            ->maxLength(255),
                        TextInput::make('occupation')
                            ->label('Pekerjaan')
                            ->maxLength(80),
                        TextInput::make('whatsapp')
                            ->label('No. WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Dipakai GrisaPay untuk notifikasi tagihan. Isi manual: Dapodik tidak punya data ini dan impor tidak akan menimpanya.')
                            ->dehydrateStateUsing(fn(?string $state): ?string => self::normalizePhone($state)),
                        Toggle::make('is_primary_contact')
                            ->label('Kontak utama tagihan'),
                    ])
                    ->columns(2)
                    ->maxItems(3)
                    ->defaultItems(0)
                    ->addActionLabel('Tambah orang tua / wali')
                    ->itemLabel(fn(array $state): ?string => filled($state['name'] ?? null) ? $state['name'] : null)
                    ->columnSpanFull(),
            ]);
    }

    /** "+62 812-3456-7890" / "812..." / "0812..." => "0812...". Kosong => null. */
    public static function normalizePhone(?string $state): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $state);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return '0' . substr($digits, 2);
        }

        return str_starts_with($digits, '8') ? '0' . $digits : $digits;
    }
}
