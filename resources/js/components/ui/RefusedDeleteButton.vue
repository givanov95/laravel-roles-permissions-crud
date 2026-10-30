<script setup lang="ts">
import { ref } from "vue";

import { t } from "../../config";
import { focusClicked } from "../../focus";
import ConfirmationModal from "./ConfirmationModal.vue";
import SecondaryButton from "./SecondaryButton.vue";

/**
 * A Delete the server would refuse: greyed out, with the reason as its hint (and its description for a
 * screen reader). A click, a tap or Enter opens the reason too, since a hint shows only on hover.
 */
defineProps<{
    /** The record's name, for the dialog's title. */
    name: string;
    /** Why the delete would be refused (`delete_refusal`), already translated on the server. */
    reason: string;
}>();

const show = ref(false);

// Focused first, so the dialog gives the focus back to it on close (see focusClicked).
const open = (event: MouseEvent) => {
    focusClicked(event);
    show.value = true;
};
</script>

<template>
    <button
        type="button"
        aria-disabled="true"
        :title="reason"
        class="cursor-not-allowed rounded-md border border-gray-200 px-2 py-1 text-sm text-gray-400"
        @click="open"
    >
        {{ t('Delete') }}
    </button>

    <!-- Mounted only while open: one per refused row otherwise, and none left behind when the row goes. -->
    <ConfirmationModal v-if="show" :show="true" @close="show = false">
        <template #title>{{ t('Cannot delete ":name"', { name }) }}</template>
        <template #content>{{ reason }}</template>
        <template #footer>
            <SecondaryButton @click="show = false">{{ t('Close') }}</SecondaryButton>
        </template>
    </ConfirmationModal>
</template>
