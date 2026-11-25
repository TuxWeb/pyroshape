<script lang="ts">
  import type { HTMLTextareaAttributes } from 'svelte/elements';

  export let value: HTMLTextareaAttributes['value'] = '';
  export let rows: HTMLTextareaAttributes['rows'] = 4;
  let className = '';
  export { className as class };
</script>

<textarea
  bind:value
  {rows}
  class={`min-h-24 w-full resize-y rounded-lg border border-input bg-white px-3 py-2 text-sm text-foreground shadow-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-4 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
  {...$$restProps}
></textarea>
