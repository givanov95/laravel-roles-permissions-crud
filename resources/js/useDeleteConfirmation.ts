import { router } from "@inertiajs/vue3";
import { nextTick, reactive, ref } from "vue";

import { r } from "./config";
import { focusAfterDelete, focusClicked } from "./focus";

/** A row of a list that can be deleted. */
export interface DeletableRow {
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

    const closeModal = () => {
        if (deleting.value) return;
        // The form is not reset: the fading modal would read `delete ""`, and the next confirmDelete
        // fills it again anyway.
        showDeleteModal.value = false;
    };

    const destroy = () => {
        if (deleting.value) return;
        // The modal keeps its own flag, set in `onStart`, which Inertia calls only for a visit that goes
        // ahead (not one a `before` listener stops).
        router.delete(r(routeName, deleteForm.id as number | string) as string, {
            preserveScroll: true,
            onStart: () => {
                deleting.value = true;
            },
            onFinish: () => {
                deleting.value = false;
                closeModal();
                // After the render: the deleted row is gone by then, and the modal has given the focus
                // back to its Delete when it is still there.
                void nextTick(() => focusAfterDelete(opener, rows.value, openerIndex, heading.value));
            },
        });
    };

    return { showDeleteModal, deleteForm, deleting, heading, rows, confirmDelete, closeModal, destroy };
}
