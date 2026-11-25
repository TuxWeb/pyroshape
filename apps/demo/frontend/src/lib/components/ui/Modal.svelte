<script lang="ts">
  import type { Snippet } from 'svelte';
  import Button from '#lib/components/ui/Button.svelte';

  let {
    open = false,
    title = '',
    description = '',
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    confirmVariant = 'primary',
    confirming = false,
    onclose,
    onconfirm,
    children
  }: {
    open?: boolean;
    title?: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmVariant?: 'primary' | 'danger';
    confirming?: boolean;
    onclose?: () => void;
    onconfirm?: () => void | Promise<void>;
    children?: Snippet;
  } = $props();
</script>

{#if open}
  <div class="fixed inset-0 z-50 grid place-items-center bg-foreground/45 px-4 py-6" role="presentation">
    <div class="w-full max-w-md rounded-lg border border-border bg-white shadow-soft" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
        <div class="grid gap-1">
          <h2 id="modal-title" class="text-base font-semibold text-foreground">{title}</h2>
          {#if description}
            <p class="text-sm text-muted-foreground">{description}</p>
          {/if}
        </div>
        <button class="rounded-lg p-1.5 text-muted-foreground hover:bg-primary-50 hover:text-primary" type="button" aria-label="Close modal" onclick={onclose}>
          x
        </button>
      </div>

      <div class="px-5 py-4 text-sm text-muted-foreground">
        {@render children?.()}
      </div>

      <div class="flex flex-col-reverse gap-2 border-t border-border px-5 py-4 sm:flex-row sm:justify-end">
        <Button variant="light" type="button" disabled={confirming} onclick={onclose}>
          {cancelLabel}
        </Button>
        <Button variant={confirmVariant} type="button" disabled={confirming} onclick={onconfirm}>
          {confirming ? 'Working...' : confirmLabel}
        </Button>
      </div>
    </div>
  </div>
{/if}
