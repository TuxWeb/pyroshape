<script lang="ts">
  import type { HTMLInputAttributes, HTMLInputTypeAttribute } from 'svelte/elements';
  import { Input } from './input';
  import { cn } from '#lib/utils.js';

  let {
    value = $bindable(''),
    type = 'text',
    class: className,
    ...props
  }: Omit<HTMLInputAttributes, 'type' | 'files'> & {
    type?: Exclude<HTMLInputTypeAttribute, 'file'>;
  } = $props();
</script>

<Input bind:value {type} class={cn('h-10 rounded-md bg-background px-3', className)} {...props} />
