<script lang="ts">
  import Alert from '#lib/components/ui/Alert.svelte';

  let { message = '' }: { message?: string } = $props();
</script>

<Alert variant="danger" {message} />
