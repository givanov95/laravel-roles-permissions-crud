<script setup lang="ts">
import type { LaravelDataTablePayload } from "@givanov95/vue-data-table/tanstack";
import { useLaravelDataTable } from "@givanov95/vue-data-table/tanstack";
import { Link, router, useForm } from "@inertiajs/vue3";
import { nextTick, ref } from "vue";

import { r, t } from "../config";
import { focusAfterDelete, focusClicked } from "../focus";
import { useServerPage } from "../page";
import ListFooter from "./ui/ListFooter.vue";
import SortableHeader from "./ui/SortableHeader.vue";
import ConfirmationModal from "./ui/ConfirmationModal.vue";
import DangerButton from "./ui/DangerButton.vue";
import PrimaryButton from "./ui/PrimaryButton.vue";
import RefusedDeleteButton from "./ui/RefusedDeleteButton.vue";
import SecondaryButton from "./ui/SecondaryButton.vue";

interface Role {
    id: number;
    name: string;
    users_count: number;
    /** Other models that have the role (missing from an older server). */
    others_count?: number;
    permissions: string[];
    is_protected: boolean;
    can_manage: boolean;
    /** Whether a delete would go through (missing from an older server: decided here as before). */
    can_delete?: boolean;
    /**
     * Why a delete would be refused, translated on the server; null when it would go through or the user
     * may not manage the record at all (missing from an older server: no reason shown).
     */
    delete_refusal?: string | null;
}

const canDelete = (role: Role): boolean => role.can_delete ?? (!role.is_protected && role.can_manage);

const props = defineProps<{
    /** `RoleController::index()`: paged, searched and sorted on the server. */
    dataTable: LaravelDataTablePayload<Role>;
}>();

const table = useLaravelDataTable<Role>(() => props.dataTable);
const { canCreate } = useServerPage();

const showDeleteModal = ref(false);
const deleteForm = useForm<{ id: number | null; name: string }>({ id: null, name: "" });

/** The heading and the rows, for the focus after a delete. */
const heading = ref<HTMLElement | null>(null);
const rows = ref<HTMLTableSectionElement | null>(null);

/** The Delete that opened the confirmation and its row's place on the page. */
let opener: HTMLElement | null = null;
let openerIndex = 0;

const confirmDelete = (role: Role, index: number, event: MouseEvent) => {
    opener = focusClicked(event);
    openerIndex = index;
    deleteForm.id = role.id;
    deleteForm.name = role.name;
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
    // `router.delete` does not set `deleteForm.processing`, so the modal keeps its own flag. Set in
    // `onStart`, which Inertia calls only for a visit that goes ahead (not one a `before` listener stops).
    router.delete(r("roles.destroy", deleteForm.id as number) as string, {
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
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 ref="heading" tabindex="-1" class="text-base font-medium text-gray-900">{{ t('Manage roles') }}</h2>
            <Link v-if="canCreate" :href="(r('roles.create') as string)">
                <PrimaryButton type="button">{{ t('Add role') }}</PrimaryButton>
            </Link>
        </div>

        <div class="border-b border-gray-100 px-6 py-3">
            <input
                type="search"
                :value="table.state.globalFilter"
                :placeholder="t('Search roles…')"
                :aria-label="t('Search roles')"
                class="block w-full max-w-xs rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                @input="table.onGlobalFilterChange(($event.target as HTMLInputElement).value)"
            />
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <SortableHeader column="name" :label="t('Name')" :sorting="table.state.sorting" @sort="table.onSortingChange" />
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Permissions') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <SortableHeader column="users_count" :label="t('Users')" :sorting="table.state.sorting" @sort="table.onSortingChange" />
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Action') }}</th>
                </tr>
            </thead>
            <tbody ref="rows" class="divide-y divide-gray-200 bg-white">
                <tr v-for="(role, index) in table.data" :key="role.id">
                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                        {{ role.name }}
                        <span v-if="role.is_protected" class="ms-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                            {{ t('Protected') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <span v-if="!role.permissions.length" class="text-gray-400">—</span>
                        <div v-else class="flex flex-wrap gap-1">
                            <span
                                v-for="permission in role.permissions"
                                :key="permission"
                                class="inline-flex items-center rounded bg-primary-50 px-2 py-0.5 text-xs text-primary-700"
                            >
                                {{ permission }}
                            </span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                        {{ role.users_count }}
                        <span v-if="role.others_count" class="text-xs text-gray-400">· {{ t(role.others_count === 1 ? ':count other' : ':count others', { count: role.others_count }) }}</span>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                        <div class="flex justify-end gap-2">
                            <Link
                                v-if="role.can_manage"
                                :href="(r('roles.edit', role.id) as string)"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm hover:bg-gray-50"
                            >
                                {{ t('Edit') }}
                            </Link>
                            <button
                                v-if="canDelete(role)"
                                type="button"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm text-red-600 hover:bg-red-50"
                                @click="confirmDelete(role, index, $event)"
                            >
                                {{ t('Delete') }}
                            </button>
                            <RefusedDeleteButton
                                v-else-if="typeof role.delete_refusal === 'string'"
                                :name="role.name"
                                :reason="role.delete_refusal"
                            />
                        </div>
                    </td>
                </tr>
                <tr v-if="!table.data.length">
                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400">{{ t('No roles found.') }}</td>
                </tr>
            </tbody>
        </table>

        <ListFooter :meta="table.meta" @page="table.goToPage" />

        <ConfirmationModal :show="showDeleteModal" @close="closeModal">
            <template #title>{{ t('Delete role') }}</template>
            <template #content>
                {{ t('Are you sure you want to delete') }} "{{ deleteForm.name }}"?
            </template>
            <template #footer>
                <!-- aria-disabled, not disabled: the focused button keeps the focus while the delete runs
                     (a disabled one drops it to the body); closeModal and destroy ignore a key press.
                     No pointer events, so the buttons do not light up on hover or press either. -->
                <SecondaryButton
                    :aria-disabled="deleting || undefined"
                    :class="{ 'pointer-events-none opacity-25': deleting }"
                    @click="closeModal"
                >
                    {{ t('Cancel') }}
                </SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :aria-disabled="deleting || undefined"
                    :class="{ 'pointer-events-none opacity-25': deleting }"
                    @click="destroy"
                >
                    {{ t('Delete') }}
                </DangerButton>
            </template>
        </ConfirmationModal>
    </div>
</template>
