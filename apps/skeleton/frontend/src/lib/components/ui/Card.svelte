<script lang="ts">
  import type { HTMLAttributes } from 'svelte/elements';
  import { Card } from './card';
  import { cn } from '$lib/utils';

  let { class: className, ...props }: HTMLAttributes<HTMLDivElement> = $props();
</script>

<Card class={cn('gap-0 rounded-lg border border-border py-0 shadow-soft ring-0', className)} {...props} />
