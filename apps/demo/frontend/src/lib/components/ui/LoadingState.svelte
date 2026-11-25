<script lang="ts">
  import Spinner from '$lib/components/ui/Spinner.svelte';

  export let label = 'Loading...';
</script>

<div class="rounded-lg border border-border bg-white px-5 py-4">
  <Spinner {label} />
</div>
