<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

import { r, t } from "../config";
import ConfirmationModal from "./ui/ConfirmationModal.vue";
import DangerButton from "./ui/DangerButton.vue";
import PrimaryButton from "./ui/PrimaryButton.vue";
import SecondaryButton from "./ui/SecondaryButton.vue";

interface Role {
    id: number;
    name: string;
    users_count: number;
    permissions: string[];
    is_protected: boolean;
}

defineProps<{
    roles: Role[];
}>();

const showDeleteModal = ref(false);
const deleteForm = useForm<{ id: number | null; name: string }>({ id: null, name: "" });

const confirmDelete = (role: Role) => {
    deleteForm.id = role.id;
    deleteForm.name = role.name;
    showDeleteModal.value = true;
};

const closeModal = () => {
    showDeleteModal.value = false;
    deleteForm.reset();
};

const destroy = () => {
    router.delete(r("roles.destroy", deleteForm.id as number) as string, {
        preserveScroll: true,
        onFinish: closeModal,
    });
};
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-base font-medium text-gray-900">{{ t('Manage roles') }}</h2>
            <Link :href="(r('roles.create') as string)">
                <PrimaryButton type="button">{{ t('Add role') }}</PrimaryButton>
            </Link>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Permissions') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Users') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ t('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="role in roles" :key="role.id">
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
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ role.users_count }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                        <div class="flex justify-end gap-2">
                            <Link
                                :href="(r('roles.edit', role.id) as string)"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm hover:bg-gray-50"
                            >
                                {{ t('Edit') }}
                            </Link>
                            <button
                                v-if="!role.is_protected"
                                type="button"
                                class="rounded-md border border-gray-200 px-2 py-1 text-sm text-red-600 hover:bg-red-50"
                                @click="confirmDelete(role)"
                            >
                                {{ t('Delete') }}
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!roles.length">
                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400">{{ t('No roles found.') }}</td>
                </tr>
            </tbody>
        </table>

        <ConfirmationModal :show="showDeleteModal" @close="closeModal">
            <template #title>{{ t('Delete role') }}</template>
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
