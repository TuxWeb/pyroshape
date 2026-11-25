<script lang="ts">
  import { Button, type ButtonProps, type ButtonVariant } from './button';
  import { cn } from '$lib/utils';

  type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
  let { variant = 'primary', class: className, ...props }: Omit<ButtonProps, 'variant'> & {
    variant?: Variant;
  } = $props();

  const variants = {
    primary: 'default',
    secondary: 'outline',
    ghost: 'ghost',
    danger: 'destructive'
  } satisfies Record<Variant, ButtonVariant>;
</script>

<Button variant={variants[variant]} class={cn('h-10 rounded-md px-4', className)} {...props} />
