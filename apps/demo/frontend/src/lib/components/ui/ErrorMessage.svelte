<script lang="ts">
  import Alert from '$lib/components/ui/Alert.svelte';

  export let message = '';
</script>

<Alert variant="danger" {message} />
