<script setup lang="ts">
import { t } from "../../config";
import ConfirmationModal from "./ConfirmationModal.vue";
import DangerButton from "./DangerButton.vue";
import SecondaryButton from "./SecondaryButton.vue";

/**
 * The confirmation of a list's delete ({@see useDeleteConfirmation}). While `deleting`, its buttons
 * look disabled but still emit on a key press: the page ignores them then (the composable's
 * `closeModal` and `destroy` do), and decides whether `close` closes the modal. Once the delete is
 * `stuck`, Cancel looks enabled again: closing then stops the delete.
 */
defineProps<{ show: boolean; title: string; name: string; deleting: boolean; stuck?: boolean }>();

defineEmits<{ close: []; confirm: [] }>();
</script>

<template>
    <ConfirmationModal :show="show" @close="$emit('close')">
        <template #title>{{ title }}</template>
        <template #content>
            {{ t('Are you sure you want to delete') }} "{{ name }}"?
        </template>
        <template #footer>
            <!-- aria-disabled, not disabled: the focused button keeps the focus while the delete runs
                 (a disabled one drops it to the body); useDeleteConfirmation's closeModal and destroy
                 ignore a key press.
                 No pointer events, so the buttons do not light up on hover or press either. -->
            <SecondaryButton
                :aria-disabled="(deleting && !stuck) || undefined"
                :class="{ 'pointer-events-none opacity-25': deleting && !stuck }"
                @click="$emit('close')"
            >
                {{ t('Cancel') }}
            </SecondaryButton>
            <DangerButton
                class="ms-3"
                :aria-disabled="deleting || undefined"
                :class="{ 'pointer-events-none opacity-25': deleting }"
                @click="$emit('confirm')"
            >
                {{ t('Delete') }}
            </DangerButton>
        </template>
    </ConfirmationModal>
</template>
