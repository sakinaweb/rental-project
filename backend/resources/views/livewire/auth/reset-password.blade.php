<div class="mx-auto max-w-md">
    <div class="card">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.auth.reset_title') }}</h1>

        <form wire:submit="save" class="mt-4 space-y-4">
            <div>
                <label class="label" for="email">{{ __('site.auth.email') }}</label>
                <input id="email" type="email" wire:model="email" class="field" autocomplete="email" required>
                @error('email')<p class="error">{{ $message }}</p>@enderror
                @error('token')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password">{{ __('site.auth.new_password') }}</label>
                <input id="password" type="password" wire:model="password" class="field" autocomplete="new-password" required>
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">{{ __('site.auth.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" class="field" autocomplete="new-password" required>
            </div>
            <button class="btn w-full" wire:loading.attr="disabled">{{ __('site.auth.save_password') }}</button>
        </form>
    </div>
</div>
