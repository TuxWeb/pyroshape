<script lang="ts">
  import type { HTMLInputAttributes } from 'svelte/elements';

  export let value: HTMLInputAttributes['value'] = '';
  export let type: HTMLInputAttributes['type'] = 'text';
  let className = '';
  export { className as class };
</script>

<input
  bind:value
  {type}
  class={`h-10 w-full rounded-lg border border-input bg-white px-3 text-sm text-foreground shadow-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-4 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
  {...$$restProps}
/>
