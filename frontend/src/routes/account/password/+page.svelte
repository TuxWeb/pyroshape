<script lang="ts">
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { ApiError, apiJson } from '$lib/api';
  import { ensureSessionLoaded } from '$lib/session';
  import Button from '$lib/components/ui/Button.svelte';
  import Card from '$lib/components/ui/Card.svelte';
  import ErrorMessage from '$lib/components/ui/ErrorMessage.svelte';
  import FormField from '$lib/components/ui/FormField.svelte';
  import Input from '$lib/components/ui/Input.svelte';
  import LoadingState from '$lib/components/ui/LoadingState.svelte';

  let currentPassword = '';
  let newPassword = '';
  let confirmPassword = '';
  let error = '';
  let success = '';
  let loading = true;
  let submitting = false;

  onMount(async () => {
    const current = await ensureSessionLoaded();
    if (!current.authenticated) {
      await goto('/login');
      return;
    }

    loading = false;
  });

  async function submit() {
    error = '';
    success = '';

    if (newPassword !== confirmPassword) {
      error = 'Password confirmation does not match';
      return;
    }

    submitting = true;
    try {
      await apiJson('/api/auth/change-password', {
        method: 'POST',
        body: JSON.stringify({ currentPassword, newPassword, confirmPassword })
      });
      currentPassword = '';
      newPassword = '';
      confirmPassword = '';
      success = 'Password changed. Your current session is still active.';
    } catch (caught) {
      error = caught instanceof ApiError ? caught.message : 'Password change failed';
    } finally {
      submitting = false;
    }
  }
</script>

{#if loading}
  <LoadingState label="Loading account..." />
{:else}
  <div class="mx-auto grid max-w-md gap-6">
    <div class="grid gap-2">
      <h1 class="text-2xl font-semibold">Change password</h1>
      <p class="text-sm text-muted-foreground">Update your Pyroshape account password.</p>
    </div>

    <Card class="p-5">
      <form class="grid gap-4" on:submit|preventDefault={submit}>
        <FormField label="Current password">
          <Input bind:value={currentPassword} type="password" autocomplete="current-password" required />
        </FormField>

        <FormField label="New password">
          <Input bind:value={newPassword} type="password" autocomplete="new-password" minlength="8" maxlength="72" required />
        </FormField>

        <FormField label="Confirm new password">
          <Input bind:value={confirmPassword} type="password" autocomplete="new-password" minlength="8" maxlength="72" required />
        </FormField>

        <ErrorMessage message={error} />

        {#if success}
          <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {success}
          </div>
        {/if}

        <Button type="submit" class="w-full" disabled={submitting}>
          {submitting ? 'Changing password...' : 'Change password'}
        </Button>
      </form>
    </Card>
  </div>
{/if}
