<script lang="ts">
  type Variant = 'info' | 'success' | 'warning' | 'danger';

  export let variant: Variant = 'info';
  export let message = '';
  let className = '';
  export { className as class };

  const variants: Record<Variant, string> = {
    info: 'border-primary-200 bg-primary-50 text-primary',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    danger: 'border-destructive/30 bg-destructive/10 text-destructive'
  };
</script>

{#if message}
  <div class={`rounded-lg border px-4 py-3 text-sm ${variants[variant]} ${className}`} role="alert">
    <slot>{message}</slot>
  </div>
{/if}
