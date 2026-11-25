<script lang="ts">
  export let label: string;
  export let error = '';
</script>

<label class="grid gap-2 text-sm font-medium text-foreground">
  <span>{label}</span>
  <slot />
  {#if error}
    <span class="text-xs font-normal text-destructive">{error}</span>
  {/if}
</label>
