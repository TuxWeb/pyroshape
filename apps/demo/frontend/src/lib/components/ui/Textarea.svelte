<script lang="ts">
  import type { HTMLTextareaAttributes } from 'svelte/elements';

  let {
    value = $bindable(''),
    rows = 4,
    class: className = '',
    ...attributes
  }: Omit<HTMLTextareaAttributes, 'children'> = $props();
</script>

<textarea
  bind:value
  {rows}
  class={`min-h-24 w-full resize-y rounded-lg border border-input bg-white px-3 py-2 text-sm text-foreground shadow-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-4 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
  {...attributes}
></textarea>
