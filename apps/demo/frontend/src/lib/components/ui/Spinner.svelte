<script lang="ts">
  export let label = 'Loading';
  let className = '';
  export { className as class };
</script>

<span class={`inline-flex items-center gap-2 text-sm text-muted-foreground ${className}`}>
  <span class="h-4 w-4 animate-spin rounded-full border-2 border-border border-t-primary"></span>
  <span>{label}</span>
</span>
