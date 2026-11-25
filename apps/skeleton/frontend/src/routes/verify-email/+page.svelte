<script lang="ts">
  import { onMount } from 'svelte';
  import { ApiError, apiJson } from '#lib/api.js';
  import Button from '#lib/components/ui/Button.svelte';
  import Card from '#lib/components/ui/Card.svelte';

  let status: 'loading' | 'success' | 'error' = 'loading';
  let message = 'Checking verification link...';

  onMount(async () => {
    const token = new URL(window.location.href).searchParams.get('token') ?? '';

    if (token.trim() === '') {
      status = 'error';
      message = 'Verification token is missing.';
      return;
    }

    try {
      await apiJson('/api/auth/verify-email?token=' + encodeURIComponent(token));
      status = 'success';
      message = 'Email verified. You can now sign in.';
    } catch (caught) {
      status = 'error';
      message = caught instanceof ApiError ? caught.message : 'Email verification failed.';
    }
  });
</script>

<div class="mx-auto max-w-md">
  <Card class="grid gap-5 p-5">
    <div class="grid gap-2">
      <h1 class="text-2xl font-semibold">Email verification</h1>
      <p
        class={`text-sm ${
          status === 'error'
            ? 'text-destructive'
            : status === 'success'
              ? 'text-primary'
              : 'text-muted-foreground'
        }`}
      >
        {message}
      </p>
    </div>

    <Button class="w-full" variant={status === 'success' ? 'primary' : 'secondary'} type="button" onclick={() => location.assign('/login')}>
      Go to login
    </Button>
  </Card>
</div>
