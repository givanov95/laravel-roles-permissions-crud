<script lang="ts">
// Module scope: one counter for every modal, so each gets its own ids. Counted in the browser only
// (on mount), since a server rendering many requests would count differently (SSR).
let lastId = 0;

const FOCUSABLE = [
    "a[href]",
    "button:not([disabled])",
    "input:not([disabled]):not([type='hidden'])",
    "select:not([disabled])",
    "textarea:not([disabled])",
    "[tabindex]:not([tabindex='-1']):not([disabled])",
].join(",");

/**
 * The Escape handlers of the open modals, the last opened last. Only that one gets the key, on the
 * window before anything else (capture), so Escape also works with the focus outside the modal (on
 * the body, after its focused button was disabled) and does not reach the app's own Escape handlers,
 * which would close a drawer or dialog around the lists as well. The page scrolls again when the
 * last one closes.
 */
const openModals: Array<(event: KeyboardEvent) => void> = [];

/** The body's own overflow before the first modal opened, given back when the last one closes. */
let bodyOverflow = "";

const onWindowKeydown = (event: KeyboardEvent) => openModals[openModals.length - 1]?.(event);
</script>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

/**
 * A native `<dialog>` opened with `showModal()`: the browser keeps Tab in it and makes the page
 * behind inert, so a screen reader's virtual cursor cannot reach the list either.
 */
const props = withDefaults(
    defineProps<{ show?: boolean; maxWidth?: string }>(),
    { show: false, maxWidth: "2xl" },
);

const emit = defineEmits<{ close: [] }>();

const close = () => emit("close");

const titleId = ref<string>();
const contentId = ref<string>();
const dialog = ref<HTMLDialogElement | null>(null);
const panel = ref<HTMLElement | null>(null);

/** The element that had focus when the modal opened (usually the button that opened it). */
let opener: HTMLElement | null = null;

// Shown ones only: focus() does nothing on a button hidden with v-show or visibility.
const focusables = (): HTMLElement[] =>
    Array.from(panel.value?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? []).filter(
        (element) => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== "hidden",
    );

// The first button is the safe one (Cancel, Close); the panel itself when there is none. Not left to
// showModal(), which focuses the panel (the first focusable element, with its tabindex). Only while
// the page still has the modal open, since it is called after the render.
const showDialog = () => {
    if (!openModals.includes(onKeydown)) return;
    if (dialog.value && !dialog.value.open) dialog.value.showModal();
    (focusables()[0] ?? panel.value)?.focus();
};

// Escape asks the page to close it. Cancelled, so the browser does not close the dialog itself: the
// page decides, and keeps it open while a delete is on its way.
const onKeydown = (event: KeyboardEvent) => {
    // An Escape during an IME composition cancels the composition, not the modal.
    if (event.key !== "Escape" || event.isComposing) return;
    event.preventDefault();
    event.stopPropagation();
    close();
};

// A close request without the Escape key (Android's back): the page decides as well.
const onCancel = (event: Event) => {
    event.preventDefault();
    close();
};

// Chrome closes the dialog anyway at a second close request with no click or key in between. Shown
// again while the page still has it open.
const onClose = () => showDialog();

const opened = () => {
    if (openModals.includes(onKeydown)) return;
    if (!openModals.length) {
        bodyOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        window.addEventListener("keydown", onWindowKeydown, true);
    }
    openModals.push(onKeydown);
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    // After the render: v-show shows the dialog and its buttons only then.
    void nextTick(showDialog);
};

const closed = () => {
    const index = openModals.indexOf(onKeydown);
    if (index === -1) return;
    openModals.splice(index, 1);
    if (!openModals.length) {
        document.body.style.overflow = bodyOverflow;
        window.removeEventListener("keydown", onWindowKeydown, true);
    }
    // Right away: while it is modal, the page behind is inert and takes no focus. The fade-out still
    // plays, with the closed dialog shown as a plain layer (its `block` class) until it ends.
    dialog.value?.close();
    const target = opener;
    opener = null;
    // Back to the button that opened it, unless it is gone (the deleted row's Delete). After the
    // render, since the page closing the modal may remove that row in the same update.
    void nextTick(() => {
        if (target?.isConnected) target.focus();
    });
};

watch(
    () => props.show,
    (shown) => (shown ? opened() : closed()),
);

// A modal mounted already open (and unmounted while open) opens and closes as well.
onMounted(() => {
    const id = ++lastId;
    titleId.value = `rp-modal-${id}-title`;
    contentId.value = `rp-modal-${id}-content`;
    if (props.show) opened();
});
onBeforeUnmount(closed);
</script>

<template>
    <teleport to="body">
        <transition leave-active-class="duration-200">
            <!-- Full screen and see-through: the overlay and the panel inside draw the modal. -->
            <dialog
                v-show="show"
                ref="dialog"
                :aria-labelledby="titleId"
                :aria-describedby="contentId"
                class="fixed inset-0 z-50 m-0 block h-full max-h-none w-full max-w-none overflow-y-auto border-0 bg-transparent px-4 py-6 backdrop:bg-transparent sm:px-0"
                @cancel="onCancel"
                @close="onClose"
            >
                <transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-show="show" class="fixed inset-0 bg-gray-500/75" @click="close" />
                </transition>

                <transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <!-- `relative`: above the overlay. Tailwind v4's `transform` alone leaves no transform, so the
                         panel stayed under it, dimmed, and a click on its buttons hit the overlay. -->
                    <div
                        v-show="show"
                        ref="panel"
                        tabindex="-1"
                        class="relative mx-auto mt-12 w-full max-w-lg transform overflow-hidden rounded-lg bg-white shadow-xl transition-all focus:outline-none sm:w-full"
                    >
                        <div class="px-6 py-4">
                            <div :id="titleId" class="text-lg font-medium text-gray-900">
                                <slot name="title" />
                            </div>
                            <div :id="contentId" class="mt-4 text-sm text-gray-600">
                                <slot name="content" />
                            </div>
                        </div>
                        <div class="flex flex-row justify-end bg-gray-100 px-6 py-4">
                            <slot name="footer" />
                        </div>
                    </div>
                </transition>
            </dialog>
        </transition>
    </teleport>
</template>
