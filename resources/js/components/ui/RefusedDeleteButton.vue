<script setup lang="ts">
import { ref } from "vue";

import { t } from "../../config";
import ConfirmationModal from "./ConfirmationModal.vue";
import SecondaryButton from "./SecondaryButton.vue";

/**
 * A Delete the server would refuse: greyed out, with the reason as its hint. A click (or a tap, or Enter)
 * opens the reason too, since a hint shows only on hover.
 */
defineProps<{
    /** The record's name, for the dialog's title. */
    name: string;
    /** Why the delete would be refused (`delete_refusal`), already translated on the server. */
    reason: string;
}>();

const show = ref(false);
</script>

<template>
    <button
        type="button"
        aria-disabled="true"
        aria-haspopup="dialog"
        :title="reason"
        class="cursor-not-allowed rounded-md border border-gray-200 px-2 py-1 text-sm text-gray-400"
        @click="show = true"
    >
        {{ t('Delete') }}<span class="sr-only">: {{ reason }}</span>
    </button>

    <ConfirmationModal :show="show" @close="show = false">
        <template #title>{{ t('Cannot delete ":name"', { name }) }}</template>
        <template #content>{{ reason }}</template>
        <template #footer>
            <SecondaryButton @click="show = false">{{ t('Close') }}</SecondaryButton>
        </template>
    </ConfirmationModal>
</template>
