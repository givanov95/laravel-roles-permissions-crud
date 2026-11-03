<script setup lang="ts">
import { t } from "../../config";
import ConfirmationModal from "./ConfirmationModal.vue";
import DangerButton from "./DangerButton.vue";
import SecondaryButton from "./SecondaryButton.vue";

/**
 * The confirmation of a list's delete ({@see useDeleteConfirmation}). While `deleting`, its buttons
 * do nothing; the page decides whether `close` closes it.
 */
defineProps<{ show: boolean; title: string; name: string; deleting: boolean }>();

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
                 (a disabled one drops it to the body); closeModal and destroy ignore a key press.
                 No pointer events, so the buttons do not light up on hover or press either. -->
            <SecondaryButton
                :aria-disabled="deleting || undefined"
                :class="{ 'pointer-events-none opacity-25': deleting }"
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
