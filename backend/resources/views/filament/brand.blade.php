{{-- Admin panel logo: gradient mark + site name (same mark as the apps). --}}
<div style="display:flex;align-items:center;gap:.6rem">
    <span style="display:grid;place-items:center;width:2.1rem;height:2.1rem;border-radius:.7rem;background:linear-gradient(135deg,#1e1b4b,#4338ca 55%,#7c3aed);box-shadow:0 6px 16px -6px rgba(67,56,202,.7)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h2l3 7.5M5.5 17.5 9 10h6l3.5 7.5M9 10l-1.5-3H5"/></svg>
    </span>
    <span style="font-weight:800;letter-spacing:-.02em;font-size:1.05rem">{{ config('app.name') }}</span>
</div>
