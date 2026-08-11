<style>
    /* Separates the sidebar from the main body visually (docs/Reference UI/Admin/Admin navigation UI.docx). */
    .fi-sidebar {
        border-inline-end: 1px solid rgb(229 231 235);
    }
    .dark .fi-sidebar {
        border-inline-end-color: rgb(31 41 55);
    }

    /* Belt-and-braces: the "Skip to content" link is already clipped to zero
       visual area via clip-path when unfocused (Filament's own utilities.css),
       but forcing opacity to 0 too guards against any stray 1px artifact at
       the topbar's top-left corner. Still fully visible on keyboard focus. */
    .fi-skip-link:not(:focus) {
        opacity: 0;
    }

    /* The "dot": <x-filament-panels::topbar.item> (used for View Site) renders
       a real <li>. Inside Filament's own nav it's wrapped in a <ul> that
       resets list-style via Tailwind's preflight, but our TOPBAR_START render
       hook (resources/views/filament/admin/topbar/start.blade.php) places it
       directly in a <div> with no such wrapper, so the browser's default
       disc marker was showing. */
    li.fi-topbar-item {
        list-style: none;
    }
</style>
