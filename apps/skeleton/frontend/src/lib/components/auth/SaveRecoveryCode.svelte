<script lang="ts">
  import Button from '#lib/components/ui/Button.svelte';

  let { code, oncontinue }: { code: string; oncontinue: () => void } = $props();
</script>

<section class="grid gap-4">
  <h2 class="text-lg font-semibold">Save your recovery code</h2>
  <p class="text-sm text-muted-foreground">
    Keep this one-time code somewhere safe and offline. You can use it to recover your account if you lose access to your authenticator. It is shown only here.
  </p>
  <code class="break-all rounded-md bg-muted px-3 py-2 font-mono">{code}</code>
  <p class="text-sm text-muted-foreground">
    Wait until your authenticator displays a new code before signing in again.
  </p>
  <Button type="button" onclick={oncontinue}>I saved the code. Continue to sign-in</Button>
</section>
