<template>
  <DefaultField :field="field" :errors="errors" :show-help-text="showHelpText">
    <template #field>
      <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500">
        UniFilePicker interface placeholder.
      </div>
    </template>
  </DefaultField>
</template>

<script>
export default {
  props: {
    resourceName: {
      type: String,
      required: true,
    },
    resourceId: {
      type: [String, Number],
      default: null,
    },
    field: {
      type: Object,
      required: true,
    },
    errors: {
      type: Object,
      required: true,
    },
    showHelpText: {
      type: Boolean,
      default: true,
    },
  },
}
</script>
