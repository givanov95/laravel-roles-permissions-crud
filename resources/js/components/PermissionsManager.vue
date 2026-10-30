<script setup lang="ts">
import type { LaravelDataTablePayload } from "@givanov95/vue-data-table/tanstack";
import { useLaravelDataTable } from "@givanov95/vue-data-table/tanstack";
import { Link, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

import { r, t } from "../config";
import { useServerPage } from "../page";
import ListFooter from "./ui/ListFooter.vue";
import SortableHeader from "./ui/SortableHeader.vue";
import ConfirmationModal from "./ui/ConfirmationModal.vue";
import DangerButton from "./ui/DangerButton.vue";
import PrimaryButton from "./ui/PrimaryButton.vue";
import RefusedDeleteButton from "./ui/RefusedDeleteButton.vue";
import SecondaryButton from "./ui/SecondaryButton.vue";

interface Permission {
    id: number;
    name: string;
    roles_count: number;
    /** Given straight to users (missing from an older server). */
    direct_count?: number;
    is_protected: boolean;
    /** Whether the user may open, edit and delete it (missing from an older server: allowed). */
    can_manage?: boolean;
    /** Whether a delete would go through (missing from an older server: decided here as before). */
    can_delete?: boolean;
    /**
     * Why a delete would be refused, translated on the server; null when it would go through or the user
     * may not manage the record at all (missing from an older server: no reason shown).
     */
    delete_refusal?: string | null;
}

const canDelete = (permission: Permission): boolean =>
    permission.can_delete ?? (!permission.is_protected && permission.can_manage !== false);

const props = defineProps<{
    /** `PermissionController::index()`: paged, searched and sorted on the server. */
    dataTable: LaravelDataTablePayload<Permission>;
}>();

const table = useLaravelDataTable<Permission>(() => props.dataTable);
const { canCreate } = useServerPage();

const showDeleteModal = ref(false);
const deleteForm = useForm<{ id: number | null; name: string }>({ id: null, name: "" });

const confirmDelete = (permission: Permission) => {
    deleteForm.id = permission.id;
    deleteForm.name = permission.name;
    showDeleteModal.value = true;
};

/** Set while a delete is on its way: the modal stays open (and its form kept) until it is done. */
const deleting = ref(false);

const closeModal = () => {
    if (deleting.value) return;
    showDeleteModal.value = false;
    deleteForm.reset();
};

const destroy = () => {
    if (deleting.value) return;
    // `router.delete` does not set `deleteForm.processing`, so the modal keeps its own flag. Set in
    // `onStart`, which Inertia calls only for a visit that goes ahead (not one a `before` listener stops).
    router.delete(r("permissions.destroy", deleteForm.id as number) as string, {
        preserveScroll: true,
        onStart: () => {
            deleting.value = true;
        },
        onFinish: () => {
            deleting.value = false;
            closeModal();
        },
    });
};
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-base font-medium text-gray-900">{{ t('Manage permissions') }}</h2>
            <Link v-if="canCreate" :href="(r('permissions.create') as string)">
                <PrimaryButton type="button">{{ t('Add permission') }}</PrimaryButton>
            </Link>
        </div>

        <div class="border-b border-gray-100 px-6 py-3">
            <input
                type="search"
                :value="table.state.globalFilter"
                :placeholder="t('Search permissions…')"
                :aria-label="t('Search permissions')"
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
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <SortableHeader column="roles_count" :label="t('Roles')" :sorting="table.state.sorting" @sort="table.onSortingChange" />
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="permission in table.data" :key="permission.id">
                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                        {{ permission.name }}
                        <span v-if="permission.is_protected" class="ms-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                            {{ t('Protected') }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                        {{ permission.roles_count }}
                        <span v-if="permission.direct_count" class="text-xs text-gray-400">· {{ t(':count direct', { count: permission.direct_count }) }}</span>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                        <div class="flex justify-end gap-2">
                            <Link
                                v-if="permission.can_manage !== false"
                                :href="(r('permissions.edit', permission.id) as string)"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm hover:bg-gray-50"
                            >
                                {{ t('Edit') }}
                            </Link>
                            <button
                                v-if="canDelete(permission)"
                                type="button"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm text-red-600 hover:bg-red-50"
                                @click="confirmDelete(permission)"
                            >
                                {{ t('Delete') }}
                            </button>
                            <RefusedDeleteButton
                                v-else-if="typeof permission.delete_refusal === 'string'"
                                :name="permission.name"
                                :reason="permission.delete_refusal"
                            />
                        </div>
                    </td>
                </tr>
                <tr v-if="!table.data.length">
                    <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-400">{{ t('No permissions found.') }}</td>
                </tr>
            </tbody>
        </table>

        <ListFooter :meta="table.meta" @page="table.goToPage" />

        <ConfirmationModal :show="showDeleteModal" @close="closeModal">
            <template #title>{{ t('Delete permission') }}</template>
            <template #content>
                {{ t('Are you sure you want to delete') }} "{{ deleteForm.name }}"?
            </template>
            <template #footer>
                <SecondaryButton :disabled="deleting" @click="closeModal">{{ t('Cancel') }}</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleting }"
                    :disabled="deleting"
                    @click="destroy"
                >
                    {{ t('Delete') }}
                </DangerButton>
            </template>
        </ConfirmationModal>
    </div>
</template>
