<script lang="ts">
  import type { HTMLInputAttributes } from 'svelte/elements';

  let {
    value = $bindable(''),
    type = 'text',
    class: className = '',
    ...attributes
  }: Omit<HTMLInputAttributes, 'children'> = $props();
</script>

<input
  bind:value
  {type}
  class={`h-10 w-full rounded-lg border border-input bg-white px-3 text-sm text-foreground shadow-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-4 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
  {...attributes}
/>
