{{--
    Styles for the Newsletter Marketing admin views. The panel has no custom
    Tailwind theme, so arbitrary utility classes in module views would not
    exist in the compiled CSS; these few scoped rules cover them instead,
    with a dark variant matching Filament's own html.dark switch.
--}}
<style>
    .nl-admin { --nl-border: rgb(229 231 235); --nl-muted: rgb(107 114 128); --nl-text: rgb(3 7 18); --nl-surface: #fff; --nl-subtle: rgb(249 250 251); --nl-accent: rgb(217 119 6); }
    .dark .nl-admin { --nl-border: rgb(255 255 255 / .1); --nl-muted: rgb(156 163 175); --nl-text: #fff; --nl-surface: rgb(255 255 255 / .05); --nl-subtle: rgb(255 255 255 / .05); }
    .nl-admin { color: var(--nl-text); font-size: .875rem; }
    .nl-admin .nl-stack > * + * { margin-top: 1rem; }
    .nl-admin .nl-dl { display: grid; grid-template-columns: max-content 1fr; gap: .5rem 1rem; margin: 0; padding: 1rem; border: 1px solid var(--nl-border); border-radius: .75rem; }
    .nl-admin .nl-dl dt { color: var(--nl-muted); font-weight: 500; }
    .nl-admin .nl-dl dd { margin: 0; overflow-wrap: anywhere; }
    .nl-admin .nl-tabs { display: flex; gap: .5rem; }
    .nl-admin .nl-tab { padding: .375rem .75rem; border-radius: .5rem; border: 1px solid var(--nl-border); background: var(--nl-surface); color: var(--nl-text); font-weight: 500; cursor: pointer; }
    .nl-admin .nl-tab[aria-selected="true"] { background: var(--nl-accent); border-color: var(--nl-accent); color: #fff; }
    .nl-admin .nl-frame-wrap { overflow-x: auto; padding: .75rem; border: 1px solid var(--nl-border); border-radius: .75rem; background: var(--nl-subtle); }
    .nl-admin .nl-frame { display: block; margin: 0 auto; border: 0; border-radius: .5rem; background: #fff; height: 640px; max-width: none; }
    .nl-admin details summary { cursor: pointer; font-weight: 500; }
    .nl-admin pre.nl-text { max-height: 20rem; overflow: auto; white-space: pre-wrap; padding: .75rem; border-radius: .5rem; background: var(--nl-subtle); font-size: .75rem; }
    .nl-admin .nl-links { margin: .5rem 0 0; padding-inline-start: 1.25rem; overflow-wrap: anywhere; font-size: .75rem; }
    .nl-admin .nl-links a { color: var(--nl-accent); text-decoration: underline; }
    .nl-admin .nl-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr)); gap: .75rem; }
    .nl-admin .nl-tile { padding: 1rem; border: 1px solid var(--nl-border); border-radius: .75rem; background: var(--nl-surface); }
    .nl-admin .nl-tile-label { color: var(--nl-muted); font-size: .75rem; font-weight: 500; }
    .nl-admin .nl-tile-value { margin-top: .25rem; font-size: 1.5rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .nl-admin .nl-tile-rate { color: var(--nl-muted); font-size: .75rem; font-variant-numeric: tabular-nums; }
    .nl-admin .nl-alert { margin-top: .75rem; padding: .75rem; border: 1px solid rgb(252 165 165); border-radius: .75rem; background: rgb(254 242 242); color: rgb(185 28 28); }
    .dark .nl-admin .nl-alert { border-color: rgb(239 68 68 / .4); background: rgb(239 68 68 / .1); color: rgb(252 165 165); }
    .nl-admin .nl-alert ul { margin: .25rem 0 0; padding-inline-start: 1.25rem; }
    .nl-admin .nl-note { margin-top: .75rem; color: var(--nl-muted); }
    @media (max-width: 640px) { .nl-admin .nl-dl { grid-template-columns: 1fr; } .nl-admin .nl-dl dd { margin-bottom: .5rem; } }
</style>
