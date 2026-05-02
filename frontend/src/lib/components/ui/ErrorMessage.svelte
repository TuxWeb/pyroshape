<script lang="ts">
  export let message = '';
</script>

{#if message}
  <p class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
    {message}
  </p>
{/if}
