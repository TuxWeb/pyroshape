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
  class={`input h-10 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground shadow-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
  {...$$restProps}
/>
