<script lang="ts">
  import type { HTMLAttributes } from 'svelte/elements';

  let { class: className = '', children, ...attributes }: HTMLAttributes<HTMLElement> = $props();
</script>

<section class={`rounded-lg border border-border bg-white shadow-soft ${className}`} {...attributes}>
  {@render children?.()}
</section>
