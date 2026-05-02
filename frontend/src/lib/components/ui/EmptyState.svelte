<script lang="ts">
  export let title: string;
  export let description: string;
</script>

<section class="grid gap-3 rounded-lg border border-dashed border-border bg-white px-5 py-8 text-center">
  <div class="grid gap-1">
    <h2 class="text-base font-semibold text-foreground">{title}</h2>
    <p class="text-sm text-muted-foreground">{description}</p>
  </div>

  <slot />
</section>
