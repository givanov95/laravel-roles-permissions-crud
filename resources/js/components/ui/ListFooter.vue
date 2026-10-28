<script setup lang="ts">
import type { LaravelDataTableMeta } from "@givanov95/vue-data-table/tanstack";

import { t } from "../../config";

/**
 * "1–20 of 45" and the previous / next page buttons of a server list.
 */
defineProps<{ meta: LaravelDataTableMeta }>();

defineEmits<{ page: [page: number] }>();
</script>

<template>
    <div class="flex items-center justify-between border-t border-gray-100 px-6 py-3 text-sm text-gray-600">
        <span>{{ t(":from–:to of :total", { from: meta.from, to: meta.to, total: meta.total }) }}</span>
        <!-- A page past the last one (a shared link after deletes): nothing to show, so offer the first. -->
        <button
            v-if="meta.current_page > meta.last_page"
            type="button"
            class="rounded-md border border-gray-200 px-2 py-1 hover:bg-gray-50"
            @click="$emit('page', 1)"
        >
            {{ t("First page") }}
        </button>
        <div v-else-if="meta.last_page > 1" class="flex gap-2">
            <button
                type="button"
                class="rounded-md border border-gray-200 px-2 py-1 hover:bg-gray-50 disabled:opacity-40"
                :disabled="meta.current_page <= 1"
                @click="$emit('page', meta.current_page - 1)"
            >
                {{ t("Previous") }}
            </button>
            <button
                type="button"
                class="rounded-md border border-gray-200 px-2 py-1 hover:bg-gray-50 disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                @click="$emit('page', meta.current_page + 1)"
            >
                {{ t("Next") }}
            </button>
        </div>
    </div>
</template>
