<script lang="ts">
  import type { Snippet } from 'svelte';

  let { label, error = '', children }: { label: string; error?: string; children?: Snippet } = $props();
</script>

<label class="grid gap-2 text-sm font-medium text-foreground">
  <span>{label}</span>
  {@render children?.()}
  {#if error}
    <span class="text-xs font-normal text-destructive">{error}</span>
  {/if}
</label>
