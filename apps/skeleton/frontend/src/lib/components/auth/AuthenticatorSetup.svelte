<script lang="ts">
  import { toCanvas } from 'qrcode';

  let { provisioningUri, manualKey }: { provisioningUri: string; manualKey: string } = $props();
  let canvas = $state<HTMLCanvasElement>();
  let qrUnavailable = $state(false);

  $effect(() => {
    if (!canvas) return;
    qrUnavailable = false;
    let active = true;

    toCanvas(canvas, provisioningUri, { width: 224 }).catch(() => {
      if (active) qrUnavailable = true;
    });

    return () => {
      active = false;
    };
  });
</script>

<div class="grid gap-3 text-sm">
  {#if !qrUnavailable}
    <p class="text-muted-foreground">Scan this QR code in your authenticator app.</p>
  {/if}
  <div hidden={qrUnavailable} role="img" aria-label="Authenticator setup QR code">
    <canvas bind:this={canvas} aria-hidden="true" class="max-w-full rounded-md border border-border bg-white"></canvas>
  </div>
  <div class="grid gap-1">
    <p class="text-muted-foreground">You can also enter this key manually as a time-based account.</p>
    <code class="break-all rounded-md bg-muted px-3 py-2 font-mono">{manualKey}</code>
  </div>
</div>
