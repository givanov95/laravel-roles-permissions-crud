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
    permission?: { id: number; name: string };
}>();

const isEdit = computed(() => !!props.permission);

const form = useForm<{ _method?: string; name: string }>({
    _method: props.permission ? "PUT" : undefined,
    name: props.permission?.name ?? "",
});

const submit = () => {
    if (props.permission) {
        form.post(r("permissions.update", props.permission.id) as string);
    } else {
        form.post(r("permissions.store") as string);
    }
};
</script>

<template>
    <form class="max-w-2xl rounded-lg bg-white p-6 shadow-sm" @submit.prevent="submit">
        <div>
            <InputLabel for="name" :value="t('Name')" />
            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
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
