<?php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AccountService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use UnitEnum;

/** Pembuatan akun login guru & siswa (untuk GrisaKAD). */
class LoginAccounts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Akun Login';

    protected static ?string $title = 'Akun Login Guru & Siswa';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.login-accounts';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'teachers' => ['total' => Teacher::query()->where('status', 'aktif')->count(), 'with' => User::query()->whereNotNull('teacher_id')->count()],
            'students' => ['total' => Student::query()->where('status', 'aktif')->count(), 'with' => User::query()->whereNotNull('student_id')->count()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('teachers')->label('Buat akun semua guru')->icon('heroicon-o-user-group')->requiresConfirmation()
                ->modalDescription('Login pakai email; password awal = tanggal lahir (ddmmyyyy), wajib diganti saat masuk pertama. Yang sudah punya akun dilewati.')
                ->action(fn () => $this->report('Akun guru', app(AccountService::class)->createTeachers())),
            Action::make('students')->label('Buat akun semua siswa aktif')->icon('heroicon-o-academic-cap')->requiresConfirmation()
                ->modalDescription('Login pakai NISN; password awal = tanggal lahir (ddmmyyyy), wajib diganti saat masuk pertama. Siswa tanpa NISN lengkap atau tanggal lahir dilewati dan dilaporkan.')
                ->action(fn () => $this->report('Akun siswa', app(AccountService::class)->createStudents())),
            Action::make('reset')->label('Reset password')->icon('heroicon-o-arrow-path')->color('gray')
                ->schema([
                    Radio::make('type')->label('Jenis')->options(['student' => 'Siswa', 'teacher' => 'Guru'])->default('student')->live()->inline(),
                    Select::make('user')->label('Akun')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search, callable $get): array => User::query()
                            ->where('account_type', $get('type') ?: 'student')
                            ->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                            ->limit(20)->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name.' · '.($u->username ?: $u->email)])->all())
                        ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->name),
                ])
                ->modalDescription('Password dikembalikan ke tanggal lahir (ddmmyyyy), wajib diganti lagi, dan semua sesi aplikasi akun itu dicabut.')
                ->action(function (array $data): void {
                    try {
                        $pw = app(AccountService::class)->reset(User::query()->findOrFail($data['user']));
                        Notification::make()->success()->title('Password direset')->body("Password sementara: {$pw} (tanggal lahir)")->duration(15000)->send();
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title('Gagal')->body($e->getMessage())->duration(10000)->send();
                    }
                }),
        ];
    }

    /** @param array{dibuat: int, ditautkan: int, sudah_ada: int, dilewati: int, warnings: list<string>} $r */
    private function report(string $title, array $r): void
    {
        Notification::make()->color($r['dilewati'] > 0 ? 'warning' : 'success')->title("{$title} selesai")
            ->body("Dibuat {$r['dibuat']} · Ditautkan {$r['ditautkan']} · Sudah ada {$r['sudah_ada']} · Dilewati {$r['dilewati']}"
                .($r['warnings'] ? "\n".Str::limit(implode('; ', array_slice($r['warnings'], 0, 3)), 300) : ''))
            ->duration(12000)->send();
    }
}
