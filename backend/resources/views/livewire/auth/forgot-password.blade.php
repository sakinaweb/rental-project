<div class="mx-auto max-w-md">
    <div class="card">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.auth.forgot_title') }}</h1>

        @if ($sent)
            <p class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ __('messages.password_reset_sent') }}</p>
        @else
            <p class="mt-2 text-sm text-zinc-600">{{ __('site.auth.forgot_intro') }}</p>
            <form wire:submit="send" class="mt-4 space-y-4">
                <div>
                    <label class="label" for="email">{{ __('site.auth.email') }}</label>
                    <input id="email" type="email" wire:model="email" class="field" autocomplete="email" required>
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn w-full" wire:loading.attr="disabled">{{ __('site.auth.send_link') }}</button>
            </form>
        @endif

        <p class="mt-6 text-center text-sm text-zinc-600"><a href="{{ route('login') }}" class="font-semibold underline">{{ __('site.auth.back_to_sign_in') }}</a></p>
    </div>
</div>
