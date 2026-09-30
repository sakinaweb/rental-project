<div class="mx-auto max-w-md">
    <form wire:submit="register" class="card space-y-4">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.auth.create_account') }}</h1>
        @foreach ([['name', 'text', 'name'], ['email', 'email', 'email'], ['phone', 'tel', 'tel']] as [$field, $type, $auto])
            <div>
                <label class="label" for="{{ $field }}">{{ __('site.auth.'.$field) }}@if ($field === 'phone') <span class="text-zinc-400">({{ __('site.common.optional') }})</span>@endif</label>
                <input id="{{ $field }}" type="{{ $type }}" wire:model="{{ $field }}" class="field" autocomplete="{{ $auto }}" @if ($field !== 'phone') required @endif>
                @if ($field === 'phone')<p class="mt-1 text-xs text-zinc-500">{{ __('site.auth.phone_hint') }}</p>@endif
                @error($field)<p class="error">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div>
            <label class="label" for="country">{{ __('site.search.country') }}</label>
            <select id="country" wire:model="country_id" class="field">
                <option value="">—</option>
                @foreach ($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            @error('country_id')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">{{ __('site.auth.password') }}</label>
            <input id="password" type="password" wire:model="password" class="field" autocomplete="new-password" required>
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">{{ __('site.auth.password_confirmation') }}</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="field" autocomplete="new-password" required>
        </div>
        <button class="btn w-full" wire:loading.attr="disabled">{{ __('site.auth.create_account') }}</button>
        <p class="text-center text-sm text-zinc-600">{{ __('site.auth.have_account') }} <a href="{{ route('login') }}" class="font-semibold underline">{{ __('site.auth.sign_in') }}</a></p>
    </form>
</div>
