/**
 * Focuses the button that was clicked and returns it. Safari and Firefox on macOS do not focus a
 * button on a click, so a modal opened from it would find the focus on the body and have nothing to
 * give it back to on close.
 */
export const focusClicked = (event: Event): HTMLElement | null => {
    const target = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    target?.focus();
    return target;
};

/**
 * Moves the focus after a delete took a list row away, so it does not drop to the body: to the
 * Delete of the row that took its place (of the one before, when the last row went), else to that
 * row's first link or button, else to the list's heading (no rows left). Does nothing when the
 * button that opened the confirmation is still on the page (the delete was refused or failed): the
 * modal gives the focus back to it. Called after the render, once the row is gone.
 */
export const focusAfterDelete = (
    opener: HTMLElement | null,
    rows: HTMLTableSectionElement | null,
    index: number,
    heading: HTMLElement | null,
): void => {
    if (opener?.isConnected) return;

    const all = rows?.rows ?? [];
    const row = all.length ? all[Math.min(index, all.length - 1)] : undefined;
    // `data-rp-delete`: the row's Delete, the active or the refused one.
    const target =
        row?.querySelector<HTMLElement>("[data-rp-delete]") ?? row?.querySelector<HTMLElement>("a[href], button") ?? heading;
    target?.focus();
};
