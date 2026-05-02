<script lang="ts">
  import type { HTMLButtonAttributes } from 'svelte/elements';

  type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

  export let variant: Variant = 'primary';
  export let type: HTMLButtonAttributes['type'] = 'button';
  let className = '';
  export { className as class };

  const variants: Record<Variant, string> = {
    primary: 'bg-primary text-primary-foreground hover:bg-primary/90',
    secondary: 'border border-border bg-white text-foreground hover:bg-muted',
    ghost: 'text-muted-foreground hover:bg-muted hover:text-foreground',
    danger: 'bg-destructive text-destructive-foreground hover:bg-destructive/90'
  };
</script>

<button
  class={`inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-medium shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 ${variants[variant]} ${className}`}
  {type}
  {...$$restProps}
>
  <slot />
</button>
