<template>
  <div class="tabs" :data-value="currentValue">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { provide, computed, ref } from 'vue'

interface Props {
  defaultValue?: string
  modelValue?: string
}

const props = withDefaults(defineProps<Props>(), {
  defaultValue: '',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

// Internal state for when no v-model is used
const internalValue = ref(props.defaultValue)

// Use modelValue if provided, otherwise use internal state
const currentValue = computed(() => props.modelValue || internalValue.value)

const setValue = (value: string) => {
  if (props.modelValue !== undefined) {
    emit('update:modelValue', value)
  } else {
    internalValue.value = value
  }
}

provide('tabs', {
  value: currentValue,
  setValue
})
</script>