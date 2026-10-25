{{-- The model's photo (admin → Vehicle models), or a drawn bike/car on the brand gradient when there is none. --}}
@props(['model', 'class' => 'aspect-[16/10]'])
<div class="{{ $class }} relative overflow-hidden bg-gradient-to-br from-brand-50 via-white to-amber-50">
    @if ($url = $model->imageUrl())
        <img src="{{ $url }}" alt="{{ $model->brand->name }} {{ $model->name }}" loading="lazy" decoding="async"
             class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
    @else
        <div class="absolute -end-8 -top-10 h-40 w-40 rounded-full bg-brand-500/10"></div>
        <div class="absolute -bottom-12 -start-6 h-32 w-32 rounded-full bg-amber-400/15"></div>
        @if ($model->category?->type === 'bike')
            <svg viewBox="0 0 200 120" aria-hidden="true" class="absolute inset-x-[15%] bottom-[12%] w-[70%] text-brand-700 transition duration-500 group-hover:-translate-x-1 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="100" cy="108" rx="88" ry="5" fill="currentColor" stroke="none" opacity=".08"/>
                <circle cx="45" cy="85" r="22"/><circle cx="45" cy="85" r="5" fill="currentColor"/>
                <circle cx="158" cy="85" r="22"/><circle cx="158" cy="85" r="5" fill="currentColor"/>
                <path d="M45 85 L80 55 L120 55 L135 85 M80 55 L70 40 M62 40 h22 M120 55 L140 30 M132 30 h20 M135 85 L158 85" />
                <path d="M85 55 Q100 40 120 47" stroke="#f59e0b" stroke-width="9"/>
            </svg>
        @else
            <svg viewBox="0 0 220 120" aria-hidden="true" class="absolute inset-x-[10%] bottom-[12%] w-[80%] text-brand-700 transition duration-500 group-hover:-translate-x-1 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="110" cy="108" rx="100" ry="5" fill="currentColor" stroke="none" opacity=".08"/>
                <path d="M18 82 V66 Q18 58 28 56 L62 50 L88 28 Q94 23 104 23 H150 Q160 23 168 31 L190 54 Q204 57 204 70 V82 Z" fill="currentColor" fill-opacity=".1"/>
                <path d="M72 50 L94 30 H124 V50 Z M132 30 H150 Q156 30 162 36 L176 50 H132 Z" fill="#fff" stroke-width="3"/>
                <circle cx="60" cy="84" r="16" fill="#fff"/><circle cx="60" cy="84" r="5" fill="currentColor"/>
                <circle cx="166" cy="84" r="16" fill="#fff"/><circle cx="166" cy="84" r="5" fill="currentColor"/>
                <path d="M192 64 h8" stroke="#f59e0b" stroke-width="6"/>
            </svg>
        @endif
    @endif
    {{ $slot }}
</div>
