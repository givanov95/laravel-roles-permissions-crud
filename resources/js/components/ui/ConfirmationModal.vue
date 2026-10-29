<script lang="ts">
// Module scope: one counter for every modal, so each title gets its own id. Counted in the browser
// only (on mount), since a server rendering many requests would count differently (SSR).
let lastId = 0;

const FOCUSABLE = [
    "a[href]",
    "button:not([disabled])",
    "input:not([disabled]):not([type='hidden'])",
    "select:not([disabled])",
    "textarea:not([disabled])",
    "[tabindex]:not([tabindex='-1'])",
].join(",");
</script>

<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const props = withDefaults(
    defineProps<{ show?: boolean; maxWidth?: string }>(),
    { show: false, maxWidth: "2xl" },
);

const emit = defineEmits<{ close: [] }>();

const close = () => emit("close");

const titleId = ref<string>();
const panel = ref<HTMLElement | null>(null);

/** The element that had focus when the modal opened (usually the button that opened it). */
let opener: HTMLElement | null = null;

const focusables = (): HTMLElement[] =>
    Array.from(panel.value?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? []);

// Escape closes; Tab and Shift+Tab go round the modal's own buttons instead of the page behind it.
// Bound on the modal, not the document, so an Escape here does not also close a drawer or dialog of
// the app around the lists.
const onKeydown = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        close();
        return;
    }
    if (event.key !== "Tab" || !panel.value) return;

    const items = focusables();
    if (!items.length) {
        event.preventDefault();
        panel.value.focus();
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    // The panel itself has the focus after a click on its text (or when it has no buttons).
    const atStart = active === first || active === panel.value || !panel.value.contains(active);
    const atEnd = active === last || !panel.value.contains(active);

    if (event.shiftKey && atStart) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && atEnd) {
        event.preventDefault();
        first.focus();
    }
};

const opened = () => {
    document.body.style.overflow = "hidden";
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    // The first button is the safe one (Cancel, Close); the panel itself when there is none.
    void nextTick(() => (focusables()[0] ?? panel.value)?.focus());
};

const closed = () => {
    document.body.style.overflow = "";
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
    titleId.value = `rp-modal-title-${++lastId}`;
    if (props.show) opened();
});
onUnmounted(() => {
    if (props.show) closed();
});
</script>

<template>
    <teleport to="body">
        <transition leave-active-class="duration-200">
            <div v-show="show" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" @keydown="onKeydown">
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
                    <div
                        v-show="show"
                        ref="panel"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="titleId"
                        tabindex="-1"
                        class="mx-auto mt-12 w-full max-w-lg transform overflow-hidden rounded-lg bg-white shadow-xl transition-all focus:outline-none sm:w-full"
                    >
                        <div class="px-6 py-4">
                            <div :id="titleId" class="text-lg font-medium text-gray-900">
                                <slot name="title" />
                            </div>
                            <div class="mt-4 text-sm text-gray-600">
                                <slot name="content" />
                            </div>
                        </div>
                        <div class="flex flex-row justify-end bg-gray-100 px-6 py-4">
                            <slot name="footer" />
                        </div>
                    </div>
                </transition>
            </div>
        </transition>
    </teleport>
</template>
