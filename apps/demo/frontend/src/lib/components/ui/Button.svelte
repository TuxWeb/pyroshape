<script lang="ts">
  import type { HTMLButtonAttributes } from 'svelte/elements';

  type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'light';

  export let variant: Variant = 'primary';
  export let type: HTMLButtonAttributes['type'] = 'button';
  let className = '';
  export { className as class };

  const variants: Record<Variant, string> = {
    primary: 'bg-primary text-primary-foreground hover:bg-primary-800 focus:ring-primary/25',
    secondary: 'border border-border bg-white text-foreground hover:bg-primary-50 hover:text-primary focus:ring-primary/15',
    ghost: 'text-muted-foreground shadow-none hover:bg-primary-50 hover:text-primary focus:ring-primary/15',
    danger: 'bg-destructive text-destructive-foreground hover:bg-destructive/90 focus:ring-destructive/25',
    light: 'border border-border bg-white text-muted-foreground hover:bg-primary-50 hover:text-primary focus:ring-primary/15'
  };
</script>

<button
  class={`inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-4 disabled:pointer-events-none disabled:opacity-50 ${variants[variant]} ${className}`}
  {type}
  on:click
  {...$$restProps}
>
  <slot />
</button>
