<?php

namespace App\Livewire\Admin;

use App\Enums\SessionStatus;
use App\Models\Exercise;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\Offline;
use App\Support\Registration;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Admin: instellingen voor de hele app (site offline, registratie, mededeling, standaardwaarden) en systeeminformatie */
#[Layout('layouts.app')]
#[Title('Instellingen')]
class Settings extends Component
{
    public string $offlineMessage = '';

    public string $announcement = '';

    public string $announcementType = 'info';

    public string $restSeconds = '';

    public string $weeklyGoalHours = '';

    public function mount(): void
    {
        Gate::authorize('admin');

        $this->offlineMessage = Offline::message();
        $this->announcement = (string) Setting::get('announcement', '');
        $this->announcementType = Setting::get('announcement_type', 'info');
        $this->restSeconds = (string) WorkoutExercise::defaultRestSeconds();

        $minutes = Setting::get('default_weekly_goal_minutes');
        $this->weeklyGoalHours = $minutes ? str_replace('.', ',', (string) round($minutes / 60, 2)) : '';
    }

    /** Site offline of weer online zetten; de tekst wordt meteen opgeslagen */
    public function toggleOffline(): void
    {
        Gate::authorize('admin');

        $this->validate(['offlineMessage' => ['nullable', 'string', 'max:500']], attributes: ['offlineMessage' => 'melding']);

        Setting::put('offline_message', trim($this->offlineMessage) ?: null);
        Offline::set(! Offline::isOn());
        $this->offlineMessage = Offline::message();
    }

    public function toggleRegistration(): void
    {
        Gate::authorize('admin');

        Registration::open(! Registration::isOpen());
    }

    public function saveAnnouncement(): void
    {
        Gate::authorize('admin');

        $this->validate(
            ['announcement' => ['nullable', 'string', 'max:500'], 'announcementType' => ['required', Rule::in(['info', 'warning'])]],
            attributes: ['announcement' => 'mededeling', 'announcementType' => 'soort'],
        );

        Setting::put('announcement', trim($this->announcement) ?: null);
        Setting::put('announcement_type', $this->announcementType);

        $this->dispatch('announcement-saved');
    }

    public function saveDefaults(): void
    {
        Gate::authorize('admin');

        $this->weeklyGoalHours = str_replace(',', '.', trim($this->weeklyGoalHours));

        $this->validate(
            ['restSeconds' => ['required', 'integer', 'between:0,900'], 'weeklyGoalHours' => ['nullable', 'numeric', 'min:0.25', 'max:40']],
            attributes: ['restSeconds' => 'rust tussen sets', 'weeklyGoalHours' => 'weekdoel'],
        );

        Setting::put('default_rest_seconds', (int) $this->restSeconds);
        Setting::put('default_weekly_goal_minutes', $this->weeklyGoalHours === '' ? null : (int) round((float) $this->weeklyGoalHours * 60));

        $this->weeklyGoalHours = str_replace('.', ',', $this->weeklyGoalHours);
        $this->dispatch('defaults-saved');
    }

    /** Helpt als een wijziging (bv. een instelling die direct in de database is aangepast) niet zichtbaar wordt */
    public function clearCache(): void
    {
        Gate::authorize('admin');

        Cache::flush();
        $this->dispatch('cache-cleared');
    }

    #[Computed]
    public function system(): array
    {
        $db = DB::connection();

        return [
            'Omgeving' => app()->environment(),
            'Debug' => config('app.debug') ? 'aan' : 'uit',
            'Laravel' => app()->version(),
            'Livewire' => InstalledVersions::getPrettyVersion('livewire/livewire'),
            'PHP' => PHP_VERSION,
            'Database' => $db->getDriverName().' '.$db->getServerVersion(),
            'Cache' => config('cache.default'),
            'Queue' => config('queue.default'),
            'Mail' => config('mail.default'),
            'Tijdzone' => config('app.timezone'),
        ];
    }

    #[Computed]
    public function totals(): array
    {
        return [
            'Gebruikers' => User::count(),
            'Standaardoefeningen' => Exercise::whereNull('user_id')->count(),
            'Eigen oefeningen' => Exercise::whereNotNull('user_id')->count(),
            'Workouts' => Workout::count(),
            'Afgeronde trainingen' => WorkoutSession::where('status', SessionStatus::Completed)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.settings');
    }
}
