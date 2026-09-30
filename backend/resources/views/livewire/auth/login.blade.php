<div class="mx-auto max-w-md">
    <div class="card">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.auth.sign_in') }}</h1>

        <div class="mt-4 grid grid-cols-2 rounded-lg bg-zinc-100 p-1 text-sm">
            <button type="button" wire:click="$set('mode', 'password')" @class(['rounded-md py-1.5 font-medium', 'bg-white shadow' => $mode === 'password'])>{{ __('site.auth.with_password') }}</button>
            <button type="button" wire:click="$set('mode', 'otp')" @class(['rounded-md py-1.5 font-medium', 'bg-white shadow' => $mode === 'otp'])>{{ __('site.auth.with_otp') }}</button>
        </div>

        @if (session('status'))<p class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif

        @if ($mode === 'password')
            <form wire:submit="login" class="mt-4 space-y-4">
                <div>
                    <label class="label" for="email">{{ __('site.auth.email') }}</label>
                    <input id="email" type="email" wire:model="email" class="field" autocomplete="email" required>
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">{{ __('site.auth.password') }}</label>
                    <input id="password" type="password" wire:model="password" class="field" autocomplete="current-password" required>
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn w-full" wire:loading.attr="disabled">{{ __('site.auth.sign_in') }}</button>
            </form>
        @else
            <form wire:submit="{{ $codeSent ? 'verifyCode' : 'sendCode' }}" class="mt-4 space-y-4">
                <div>
                    <label class="label" for="phone">{{ __('site.auth.phone') }}</label>
                    <input id="phone" type="tel" wire:model="phone" class="field" placeholder="+919900000000" autocomplete="tel" required>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('site.auth.phone_hint') }}</p>
                    @error('phone')<p class="error">{{ $message }}</p>@enderror
                </div>
                @if ($codeSent)
                    <div>
                        <label class="label" for="code">{{ __('site.auth.code') }}</label>
                        <input id="code" type="text" inputmode="numeric" maxlength="6" wire:model="code" class="field tracking-widest" autocomplete="one-time-code" required>
                        @error('code')<p class="error">{{ $message }}</p>@enderror
                        @error('email')<p class="error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <button class="btn w-full" wire:loading.attr="disabled">{{ $codeSent ? __('site.auth.verify') : __('site.auth.send_code') }}</button>
            </form>
        @endif

        <p class="mt-6 text-center text-sm text-zinc-600">{{ __('site.auth.no_account') }} <a href="{{ route('register') }}" class="font-semibold underline">{{ __('site.auth.create_account') }}</a></p>
    </div>
</div>
