<script lang="ts">
  import { Progress } from '@skeletonlabs/skeleton-svelte';

  export let label = 'Loading...';
</script>

<div class="flex items-center gap-3 rounded-lg border border-border bg-white px-5 py-4 text-sm text-muted-foreground">
  <Progress value={null} aria-label={label}>
    <Progress.Circle class="size-5">
      <Progress.CircleTrack />
      <Progress.CircleRange />
    </Progress.Circle>
  </Progress>
  <span role="status">{label}</span>
</div>
