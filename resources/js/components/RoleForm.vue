<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import { computed } from "vue";
import Select from "@givanov95/vue-forms/components/Select.vue";

import { r, t } from "../config";
import InputError from "./ui/InputError.vue";
import InputLabel from "./ui/InputLabel.vue";
import PrimaryButton from "./ui/PrimaryButton.vue";
import SecondaryButton from "./ui/SecondaryButton.vue";
import TextInput from "./ui/TextInput.vue";

interface RoleData {
    id: number;
    name: string;
    permissions: number[];
    is_protected: boolean;
}

const props = defineProps<{
    role?: RoleData;
    permissions: { name: string; value: number }[];
}>();

const isEdit = computed(() => !!props.role);

const form = useForm<{ _method?: string; name: string; permissions: number[] }>({
    _method: props.role ? "PUT" : undefined,
    name: props.role?.name ?? "",
    permissions: props.role ? [...props.role.permissions] : [],
});

const submit = () => {
    if (props.role) {
        form.post(r("roles.update", props.role.id) as string);
    } else {
        form.post(r("roles.store") as string);
    }
};
</script>

<template>
    <form class="max-w-2xl rounded-lg bg-white p-6 shadow-sm" @submit.prevent="submit">
        <div class="space-y-6">
            <div>
                <InputLabel for="name" :value="t('Name')" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    :disabled="role?.is_protected ?? false"
                    required
                    :autofocus="!isEdit"
                />
                <p v-if="role?.is_protected" class="mt-1 text-xs text-gray-500">
                    {{ t('This role is protected; its name cannot be changed.') }}
                </p>
                <InputError :message="form.errors.name" class="mt-1" />
            </div>

            <div>
                <InputLabel for="permissions" :value="t('Permissions')" />
                <Select
                    id="permissions"
                    name="permissions"
                    v-model="form.permissions"
                    :options="permissions"
                    :placeholder="t('Select permissions')"
                    multiple
                    class="mt-1"
                />
                <InputError :message="form.errors.permissions" class="mt-1" />
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <Link :href="(r('roles.index') as string)">
                <SecondaryButton type="button">{{ t('Cancel') }}</SecondaryButton>
            </Link>
            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ isEdit ? t('Save') : t('Create') }}
            </PrimaryButton>
        </div>
    </form>
</template>
