<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <img
        src="/img/logo-icon.webp"
        alt="Alquiler Fácil"
        :class="$props.class"
        v-bind="$attrs"
    />
</template>
