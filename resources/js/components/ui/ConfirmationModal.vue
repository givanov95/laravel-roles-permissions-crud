<script setup lang="ts">
import { watch } from "vue";

const props = withDefaults(
    defineProps<{ show?: boolean; maxWidth?: string }>(),
    { show: false, maxWidth: "2xl" },
);

const emit = defineEmits<{ close: [] }>();

const close = () => emit("close");

watch(
    () => props.show,
    (shown) => {
        document.body.style.overflow = shown ? "hidden" : "";
    },
);
</script>

<template>
    <teleport to="body">
        <transition leave-active-class="duration-200">
            <div v-show="show" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
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
                        class="mx-auto mt-12 w-full max-w-lg transform overflow-hidden rounded-lg bg-white shadow-xl transition-all sm:w-full"
                    >
                        <div class="px-6 py-4">
                            <div class="text-lg font-medium text-gray-900">
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
