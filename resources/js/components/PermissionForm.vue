<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import { computed } from "vue";

import { r, t } from "../config";
import InputError from "./ui/InputError.vue";
import InputLabel from "./ui/InputLabel.vue";
import PrimaryButton from "./ui/PrimaryButton.vue";
import SecondaryButton from "./ui/SecondaryButton.vue";
import TextInput from "./ui/TextInput.vue";

const props = defineProps<{
    /** `can_rename` is missing from an older server: the name is then editable as before. */
    permission?: { id: number | string; name: string; is_protected: boolean; can_rename?: boolean };
}>();

const isEdit = computed(() => !!props.permission);

const form = useForm<{ _method?: string; name: string }>({
    _method: props.permission ? "PUT" : undefined,
    name: props.permission?.name ?? "",
});

const submit = () => {
    if (props.permission) {
        form.post(r("permissions.update", props.permission.id) as string, { preserveState: "errors" });
    } else {
        form.post(r("permissions.store") as string);
    }
};
</script>

<template>
    <form class="max-w-2xl rounded-lg bg-white p-6 shadow-sm" @submit.prevent="submit">
        <div>
            <InputLabel for="name" :value="t('Name')" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                :disabled="(permission?.is_protected ?? false) || permission?.can_rename === false"
                required
                :autofocus="!isEdit"
            />
            <p v-if="permission?.is_protected" class="mt-1 text-xs text-gray-500">
                {{ t('This permission is protected; its name cannot be changed.') }}
            </p>
            <p v-else-if="permission?.can_rename === false" class="mt-1 text-xs text-gray-500">
                {{ t('This permission is in use, so it can be renamed only to a permission you hold yourself; a new name needs access to every permission.') }}
            </p>
            <InputError :message="form.errors.name" class="mt-1" />
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <Link :href="(r('permissions.index') as string)">
                <SecondaryButton type="button">{{ t('Cancel') }}</SecondaryButton>
            </Link>
            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ isEdit ? t('Save') : t('Create') }}
            </PrimaryButton>
        </div>
    </form>
</template>
