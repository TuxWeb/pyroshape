<script lang="ts">
  import Spinner from '#lib/components/ui/Spinner.svelte';

  let { label = 'Loading...' }: { label?: string } = $props();
</script>

<div class="rounded-lg border border-border bg-white px-5 py-4">
  <Spinner {label} />
</div>
