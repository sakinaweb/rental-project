<div class="mx-auto max-w-lg">
    <form wire:submit="save" class="card space-y-4">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.nav.profile') }}</h1>
        <div>
            <span class="label">{{ __('site.auth.email') }}</span>
            <p class="text-sm text-zinc-700">{{ $email }}</p>
        </div>
        <div>
            <label class="label" for="name">{{ __('site.auth.name') }}</label>
            <input id="name" type="text" wire:model="name" class="field" required>
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="phone">{{ __('site.auth.phone') }}</label>
            <input id="phone" type="tel" wire:model="phone" class="field" placeholder="+919900000000">
            <p class="mt-1 text-xs text-zinc-500">{{ __('site.auth.phone_hint') }}</p>
            @error('phone')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="country">{{ __('site.search.country') }}</label>
            <select id="country" wire:model="country_id" class="field">
                <option value="">—</option>
                @foreach ($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            @error('country_id')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="locale">{{ __('site.nav.language') }}</label>
            <select id="locale" wire:model="locale" class="field">
                @foreach (config('app.locale_names') as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('locale')<p class="error">{{ $message }}</p>@enderror
        </div>
        @if (session('profile_saved'))<p class="text-sm text-emerald-700">{{ __('site.profile.saved') }}</p>@endif
        <button class="btn w-full" wire:loading.attr="disabled">{{ __('site.profile.save') }}</button>
    </form>
</div>
