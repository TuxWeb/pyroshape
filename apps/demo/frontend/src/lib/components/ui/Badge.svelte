<script lang="ts">
  import type { Snippet } from 'svelte';

  type Variant = 'blue' | 'green' | 'gray' | 'red' | 'amber';

  let { variant = 'gray', class: className = '', children }: {
    variant?: Variant;
    class?: string;
    children?: Snippet;
  } = $props();

  const variants: Record<Variant, string> = {
    blue: 'bg-primary-50 text-primary ring-primary-200',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    gray: 'bg-muted text-muted-foreground ring-border',
    red: 'bg-destructive/10 text-destructive ring-destructive/20',
    amber: 'bg-amber-50 text-amber-700 ring-amber-200'
  };
</script>

<span class={`inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${variants[variant]} ${className}`}>
  {@render children?.()}
</span>
