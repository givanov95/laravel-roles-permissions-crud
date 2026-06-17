<script setup lang="ts">
withDefaults(defineProps<{ type?: "button" | "submit" | "reset" }>(), {
    type: "submit",
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 active:bg-primary-800 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
