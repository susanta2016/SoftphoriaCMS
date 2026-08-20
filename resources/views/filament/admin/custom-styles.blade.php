<style>
    /* Separates the sidebar from the main body visually, per docs/Reference
       UI/Admin/Admin navigation UI.docx. */
    .fi-sidebar {
        border-inline-end: 1px solid rgb(229 231 235);
    }
    .dark .fi-sidebar {
        border-inline-end-color: rgb(31 41 55);
    }

    /* The "Skip to content" link is already clipped to zero visual area via
       clip-path when unfocused (Filament's own utilities.css), but forcing
       opacity to 0 too guards against any stray 1px artifact at the topbar's
       top-left corner. Still fully visible on keyboard focus. */
    .fi-skip-link:not(:focus) {
        opacity: 0;
    }

    /* The topbar.item component (used for View Site) renders a real list
       item element. Inside Filament's own nav it's wrapped in a list
       container that resets list-style via Tailwind's preflight, but our
       TOPBAR_START render hook (topbar/start.blade.php) places it directly
       in a plain div with no such wrapper, so the browser's default disc
       marker was showing. */
    li.fi-topbar-item {
        list-style: none;
    }

    /* Every List/Create/Edit/View page's own header — breadcrumb, title,
       and header actions like Save/Create — stays reachable while
       scrolling a long table or form instead of scrolling out of view.
       Sticks just below the topbar (.fi-topbar's min-h-16 = 4rem) on the
       same flat page background so content scrolling underneath doesn't
       show through. */
    .fi-header {
        position: sticky;
        top: 4rem;
        z-index: 20;
        background-color: rgb(249 250 251);
        padding-bottom: 1rem;
        border-bottom: 1px solid rgb(229 231 235);
    }
    .dark .fi-header {
        background-color: rgb(3 7 18);
        border-bottom-color: rgb(31 41 55);
    }
</style>
