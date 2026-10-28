<script setup lang="ts">
import { computed } from "vue";

/**
 * A column header that sorts the server list: the first click sorts ascending, the next one flips it.
 */
const props = defineProps<{
    column: string;
    label: string;
    /** The list's applied sort (`useLaravelDataTable().state.sorting`). */
    sorting: Array<{ id: string; desc: boolean }>;
}>();

const emit = defineEmits<{ sort: [sorting: Array<{ id: string; desc: boolean }>] }>();

const current = computed(() => props.sorting.find((sort) => sort.id === props.column));

const toggle = () => {
    emit("sort", [{ id: props.column, desc: current.value ? !current.value.desc : false }]);
};
</script>

<template>
    <button type="button" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700" @click="toggle">
        {{ label }}
        <span v-if="current" aria-hidden="true">{{ current.desc ? "↓" : "↑" }}</span>
    </button>
</template>
