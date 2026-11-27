<script lang="ts">
  import FormField from '#lib/components/ui/FormField.svelte';
  import Input from '#lib/components/ui/Input.svelte';

  let { label = 'Authenticator code', value = $bindable('') }: { label?: string; value?: string } = $props();
</script>

<FormField {label}>
  <Input bind:value type="text" inputmode="numeric" autocomplete="one-time-code" pattern={"[0-9]{6}"} maxlength={6} required />
</FormField>
