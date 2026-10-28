{{-- Admin polish without a separate theme build: softer surfaces, rounder cards, gradient accents. --}}
<style>
    .fi-body { background: #f5f6fa; }
    .dark .fi-body { background: #0b1020; }
    .fi-sidebar { background: #ffffff; border-inline-end: 1px solid #eef0f5; }
    .dark .fi-sidebar { background: #0f172a; border-color: #1e293b; }
    .fi-sidebar-item-active > a, .fi-sidebar-item.fi-active > a { background: linear-gradient(90deg, rgba(79,70,229,.12), rgba(124,58,237,.06)); }
    .fi-sidebar-group-label { letter-spacing: .08em; text-transform: uppercase; font-size: .7rem; }
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-wi-widget > .fi-section {
        border-radius: 1.25rem !important;
        box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .12) !important;
        --tw-ring-color: rgba(15, 23, 42, .04);
    }
    .fi-wi-stats-overview-stat { position: relative; overflow: hidden; }
    .fi-wi-stats-overview-stat::before {
        content: ""; position: absolute; inset: 0 0 auto 0; height: 3px;
        background: linear-gradient(90deg, #1e1b4b, #4338ca, #7c3aed);
    }
    .fi-header-heading { letter-spacing: -.03em; font-weight: 800; }
    .fi-btn { border-radius: .8rem; }
    .fi-topbar > nav { background: rgba(255,255,255,.8); backdrop-filter: blur(12px); box-shadow: none; border-bottom: 1px solid #eef0f5; }
    .dark .fi-topbar > nav { background: rgba(15,23,42,.8); border-color: #1e293b; }
    /* Sign-in page: brand gradient behind the card. */
    .fi-simple-layout { background: radial-gradient(1200px 600px at 10% -10%, #7c3aed33, transparent), linear-gradient(135deg, #1e1b4b, #312e81 50%, #4c1d95); }
    .fi-simple-main { border-radius: 1.5rem !important; box-shadow: 0 30px 80px -20px rgba(0,0,0,.5) !important; }
</style>
