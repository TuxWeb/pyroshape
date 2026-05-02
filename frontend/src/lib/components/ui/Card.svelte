<script lang="ts">
  let className = '';
  export { className as class };
</script>

<section class={`rounded-lg border border-border bg-white shadow-soft ${className}`} {...$$restProps}>
  <slot />
</section>
