<script lang="ts">
  let className = '';
  export { className as class };
</script>

<div class={`overflow-hidden rounded-lg border border-border bg-white shadow-sm ${className}`}>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm text-foreground">
      <slot />
    </table>
  </div>
</div>
