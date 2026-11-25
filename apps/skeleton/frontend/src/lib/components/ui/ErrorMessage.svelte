<script lang="ts">
  import { Alert, AlertDescription } from './alert';

  let { message = '' }: { message?: string } = $props();
</script>

{#if message}
  <Alert variant="destructive" class="rounded-md border-destructive/30 bg-destructive/10 px-3 py-2">
    <AlertDescription>{message}</AlertDescription>
  </Alert>
{/if}
