<script lang="ts">
  import type { Snippet } from 'svelte';

  let { class: className = '', children }: { class?: string; children?: Snippet } = $props();
</script>

<div class={`overflow-hidden rounded-lg border border-border bg-white shadow-sm ${className}`}>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm text-foreground">
      {@render children?.()}
    </table>
  </div>
</div>
