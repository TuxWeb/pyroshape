<script lang="ts">
  import { Spinner } from './spinner';

  let { label = 'Loading...' }: { label?: string } = $props();
</script>

<div class="flex items-center gap-3 rounded-lg border border-border bg-white px-5 py-4 text-sm text-muted-foreground">
  <Spinner class="size-5 shrink-0" aria-hidden="true" />
  <span role="status">{label}</span>
</div>
