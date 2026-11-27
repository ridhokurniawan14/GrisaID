<?php

namespace App\Filament\Resources\Teachers;

use App\Filament\Resources\Teachers\Pages\CreateTeacher;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Models\Teacher;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Guru';

    protected static ?string $modelLabel = 'guru';

    protected static ?string $pluralModelLabel = 'guru';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nama (tanpa gelar)')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('front_title')->label('Gelar depan')->placeholder('Drs., Hj.')->maxLength(50),
            TextInput::make('back_title')->label('Gelar belakang')->placeholder('S.Pd., M.Pd.')->maxLength(100),
            Select::make('gender')->label('Jenis kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
            TextInput::make('email')->email()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('phone')->label('No. HP')->tel()->maxLength(20),
            TextInput::make('nik')->label('NIK')->maxLength(16)->helperText('Disimpan terenkripsi.'),
            TextInput::make('nuptk')->label('NUPTK (boleh kosong)')->unique(ignoreRecord: true)->maxLength(20),
            TextInput::make('nip')->label('NIP')->maxLength(25),
            TextInput::make('birth_place')->label('Tempat lahir'),
            DatePicker::make('birth_date')->label('Tanggal lahir'),
            TextInput::make('employment_status')->label('Status kepegawaian'),
            TextInput::make('additional_duty')->label('Tugas tambahan'),
            Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                // Isi gelar langsung dari tabel (Dapodik tidak memuat gelar).
                TextInputColumn::make('front_title')->label('Gelar depan')->placeholder('—')->extraInputAttributes(['style' => 'min-width:5rem']),
                TextInputColumn::make('back_title')->label('Gelar belakang')->placeholder('—')->extraInputAttributes(['style' => 'min-width:7rem']),
                // Klik = salin (tidak membuka halaman edit). Email = username login guru.
                ViewColumn::make('email')->label('Email (username)')->view('filament.columns.copy')->searchable()->toggleable(),
                TextColumn::make('phone')->label('HP')->toggleable(),
                TextColumn::make('additional_duty')->label('Tugas tambahan')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->color(fn(string $state): string => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->filters([SelectFilter::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeachers::route('/'),
            'create' => CreateTeacher::route('/create'),
            'edit' => EditTeacher::route('/{record}/edit'),
        ];
    }
}
