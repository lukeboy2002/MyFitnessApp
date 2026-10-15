{{-- Route: Route::view('profile', 'profile.edit')->middleware(['auth', 'verified'])->name('profile.edit'); --}}
<x-app-layout title="Profiel">
    <x-page-header title="Profiel" :description="auth()->user()->email"/>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <div class="contents lg:flex lg:min-w-0 lg:flex-col lg:gap-5">
            <livewire:profile.update-profile-information/>
            <livewire:profile.update-password/>

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <livewire:profile.two-factor-authentication/>
            @endif

            @if (Laravel\Fortify\Features::canManagePasskeys())
                <livewire:profile.passkeys/>
            @endif

            <livewire:profile.delete-user/>
        </div>

        <div class="contents lg:flex lg:min-w-0 lg:flex-col lg:gap-5">
            <div class="order-first lg:order-none">
                <livewire:profile.update-profile-photo/>
            </div>

            <x-card title="Thema" description="Kies light, dark of volg je apparaat.">
                <x-theme-toggle variant="segmented"/>
            </x-card>


        </div>
    </div>
</x-app-layout>
