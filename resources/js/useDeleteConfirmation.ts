import { router } from "@inertiajs/vue3";
import { nextTick, onScopeDispose, reactive, ref } from "vue";

import { r } from "./config";
import { focusAfterDelete, focusClicked } from "./focus";

/**
 * How long a delete may go without an answer before the modal lets the user stop it. A delete takes
 * well under a second; one that takes longer has most likely hung (a lost connection, a lock in the
 * database), and the modal would otherwise stay open until the page is reloaded.
 */
const STUCK_AFTER_MS = 5000;

/** A row of a list that can be deleted. */
interface DeletableRow {
    id: number | string;
    name: string;
}

/**
 * The delete confirmation of a list (`RolesManager`, `PermissionsManager`): the modal's state, the
 * delete itself and the focus afterwards. `routeName` is the destroy route without the prefix, e.g.
 * `roles.destroy`. Bind `heading` and `rows` with `ref` to the list's heading and `<tbody>`: the focus
 * goes there after a delete took the row away.
 */
export function useDeleteConfirmation(routeName: string) {
    const showDeleteModal = ref(false);
    const deleteForm = reactive<{ id: number | string | null; name: string }>({ id: null, name: "" });

    /** The heading and the rows, for the focus after a delete. */
    const heading = ref<HTMLElement | null>(null);
    const rows = ref<HTMLTableSectionElement | null>(null);

    /** The Delete that opened the confirmation and its row's place on the page. */
    let opener: HTMLElement | null = null;
    let openerIndex = 0;

    const confirmDelete = (row: DeletableRow, index: number, event: MouseEvent) => {
        opener = focusClicked(event);
        openerIndex = index;
        deleteForm.id = row.id;
        deleteForm.name = row.name;
        showDeleteModal.value = true;
    };

    /** Set while a delete is on its way: the modal stays open (and its form kept) until it is done. */
    const deleting = ref(false);

    /** Set when the delete has gone {@link STUCK_AFTER_MS} without an answer: closing the modal stops it. */
    const stuck = ref(false);
    let stopDelete: (() => void) | null = null;
    /** Whether the delete was stopped from the modal (not by Inertia, e.g. on the browser's Back). */
    let stoppedByUser = false;
    let stuckTimer: ReturnType<typeof setTimeout> | undefined;

    /** Set once the list is gone: an async delete may end after it, on another page. */
    let disposed = false;

    onScopeDispose(() => {
        disposed = true;
        clearTimeout(stuckTimer);
    });

    const closeModal = () => {
        if (deleting.value) {
            // Inertia then calls onFinish, which closes the modal.
            if (stuck.value && stopDelete) {
                stoppedByUser = true;
                stopDelete();
            }
            return;
        }
        // The form is not reset: the fading modal would read `delete ""`, and the next confirmDelete
        // fills it again anyway.
        showDeleteModal.value = false;
    };

    /** Moves the focus off a row the delete took away (see {@link focusAfterDelete}), after the render. */
    const focusAfter = () => void nextTick(() => focusAfterDelete(opener, rows.value, openerIndex, heading.value));

    const destroy = () => {
        if (deleting.value || deleteForm.id === null) return;
        // Whether the server answered: with a page (the list, the form's errors) or an error response.
        let answered = false;
        stoppedByUser = false;
        // The modal keeps its own flag, set in `onStart`, which Inertia calls only for a visit that goes
        // ahead (not one a `before` listener stops).
        router.delete(r(routeName, deleteForm.id) as string, {
            preserveScroll: true,
            // Async, so another visit (the list's search, after its debounce) does not cancel it: the
            // record could be gone on the server while the list said nothing. Inertia hides the progress
            // bar for an async visit unless asked.
            async: true,
            showProgress: true,
            onCancelToken: ({ cancel }) => {
                stopDelete = cancel;
            },
            onSuccess: () => {
                answered = true;
            },
            onError: () => {
                answered = true;
            },
            onHttpException: () => {
                answered = true;
            },
            onStart: () => {
                deleting.value = true;
                stuckTimer = setTimeout(() => (stuck.value = true), STUCK_AFTER_MS);
            },
            onFinish: (visit) => {
                clearTimeout(stuckTimer);
                stuck.value = false;
                stopDelete = null;
                deleting.value = false;
                closeModal();
                if (disposed) return;
                // Ended without a page: stopped from the modal, a lost connection, or a redirect Inertia
                // drops for an async visit after a deploy (a new asset version). The server may still
                // have deleted the record, so the list is read again: with a plain visit, which shows the
                // progress bar and loads the whole page on a new version (`router.reload()` is async and
                // would drop it too). Not when Inertia cancelled it itself (the browser's Back).
                if (!answered && (stoppedByUser || !visit.cancelled)) {
                    router.visit(window.location.href, { preserveScroll: true, preserveState: true, onFinish: focusAfter });
                    return;
                }
                // After the render: the deleted row is gone by then, and the modal has given the focus
                // back to its Delete when it is still there.
                focusAfter();
            },
        });
    };

    return { showDeleteModal, deleteForm, deleting, stuck, heading, rows, confirmDelete, closeModal, destroy };
}
