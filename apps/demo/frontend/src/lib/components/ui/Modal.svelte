<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import Button from '#lib/components/ui/Button.svelte';

  export let open = false;
  export let title = '';
  export let description = '';
  export let confirmLabel = 'Confirm';
  export let cancelLabel = 'Cancel';
  export let confirmVariant: 'primary' | 'danger' = 'primary';
  export let confirming = false;

  const dispatch = createEventDispatcher<{
    close: void;
    confirm: void;
  }>();
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
        <button class="rounded-lg p-1.5 text-muted-foreground hover:bg-primary-50 hover:text-primary" type="button" aria-label="Close modal" on:click={() => dispatch('close')}>
          x
        </button>
      </div>

      <div class="px-5 py-4 text-sm text-muted-foreground">
        <slot />
      </div>

      <div class="flex flex-col-reverse gap-2 border-t border-border px-5 py-4 sm:flex-row sm:justify-end">
        <Button variant="light" type="button" disabled={confirming} on:click={() => dispatch('close')}>
          {cancelLabel}
        </Button>
        <Button variant={confirmVariant} type="button" disabled={confirming} on:click={() => dispatch('confirm')}>
          {confirming ? 'Working...' : confirmLabel}
        </Button>
      </div>
    </div>
  </div>
{/if}
