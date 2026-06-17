<script setup lang="ts">
import { onMounted, ref } from "vue";

const props = withDefaults(
    defineProps<{
        modelValue: string;
        id?: string;
        type?: string;
        required?: boolean;
        disabled?: boolean;
        autofocus?: boolean;
    }>(),
    {
        type: "text",
        required: false,
        disabled: false,
        autofocus: false,
    },
);

defineEmits<{ "update:modelValue": [value: string] }>();

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (props.autofocus) {
        input.value?.focus();
    }
});
</script>

<template>
    <input
        :id="id"
        ref="input"
        :type="type"
        :value="modelValue"
        :required="required"
        :disabled="disabled"
        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-500"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
</template>
