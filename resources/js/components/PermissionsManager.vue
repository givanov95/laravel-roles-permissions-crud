<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

import { r, t } from "../config";
import ConfirmationModal from "./ui/ConfirmationModal.vue";
import DangerButton from "./ui/DangerButton.vue";
import PrimaryButton from "./ui/PrimaryButton.vue";
import SecondaryButton from "./ui/SecondaryButton.vue";

interface Permission {
    id: number;
    name: string;
    roles_count: number;
}

defineProps<{
    permissions: Permission[];
}>();

const showDeleteModal = ref(false);
const deleteForm = useForm<{ id: number | null; name: string }>({ id: null, name: "" });

const confirmDelete = (permission: Permission) => {
    deleteForm.id = permission.id;
    deleteForm.name = permission.name;
    showDeleteModal.value = true;
};

const closeModal = () => {
    showDeleteModal.value = false;
    deleteForm.reset();
};

const destroy = () => {
    router.delete(r("permissions.destroy", deleteForm.id as number) as string, {
        preserveScroll: true,
        onFinish: closeModal,
    });
};
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-base font-medium text-gray-900">{{ t('Manage permissions') }}</h2>
            <Link :href="(r('permissions.create') as string)">
                <PrimaryButton type="button">{{ t('Add permission') }}</PrimaryButton>
            </Link>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Roles') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="permission in permissions" :key="permission.id">
                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ permission.name }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ permission.roles_count }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                        <div class="flex justify-end gap-2">
                            <Link
                                :href="(r('permissions.edit', permission.id) as string)"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm hover:bg-gray-50"
                            >
                                {{ t('Edit') }}
                            </Link>
                            <button
                                type="button"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm text-red-600 hover:bg-red-50"
                                @click="confirmDelete(permission)"
                            >
                                {{ t('Delete') }}
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!permissions.length">
                    <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-400">{{ t('No permissions found.') }}</td>
                </tr>
            </tbody>
        </table>

        <ConfirmationModal :show="showDeleteModal" @close="closeModal">
            <template #title>{{ t('Delete permission') }}</template>
            <template #content>
                {{ t('Are you sure you want to delete') }} "{{ deleteForm.name }}"?
            </template>
            <template #footer>
                <SecondaryButton @click="closeModal">{{ t('Cancel') }}</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="destroy"
                >
                    {{ t('Delete') }}
                </DangerButton>
            </template>
        </ConfirmationModal>
    </div>
</template>
