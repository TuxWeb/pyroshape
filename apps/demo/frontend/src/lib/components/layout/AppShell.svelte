<script lang="ts">
  import { goto } from '$app/navigation';
  import { session, logout } from '#lib/session.ts';
  import Alert from '#lib/components/ui/Alert.svelte';
  import Button from '#lib/components/ui/Button.svelte';

  let logoutError = '';
  let loggingOut = false;
  let mobileNavOpen = false;

  const navItems = [{ href: '/projects', label: 'Projects' }];

  async function signOut() {
    logoutError = '';
    loggingOut = true;

    try {
      await logout();
      mobileNavOpen = false;
      await goto('/login');
    } catch {
      logoutError = 'Logout failed. Please try again.';
    } finally {
      loggingOut = false;
    }
  }
</script>

<div class="min-h-screen bg-background text-foreground">
  <header class="sticky top-0 z-40 border-b border-border bg-white/95 shadow-sm backdrop-blur">
    <nav class="mx-auto flex min-h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
      <a href="/" class="flex min-w-0 items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow-sm">P</span>
        <span class="grid min-w-0">
          <span class="truncate text-sm font-semibold sm:text-base">Pyroshape Demo</span>
          <span class="hidden truncate text-xs text-muted-foreground sm:block">Slim API + SvelteKit SPA</span>
        </span>
      </a>

      {#if $session.authenticated}
        <div class="hidden items-center gap-1 md:flex">
          {#each navItems as item}
            <a class="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-primary-50 hover:text-primary" href={item.href}>
              {item.label}
            </a>
          {/each}
        </div>
      {/if}

      <div class="flex items-center gap-2">
        {#if $session.authenticated}
          <span class="hidden max-w-[14rem] truncate text-sm text-muted-foreground lg:block">{$session.email}</span>
          <Button variant="light" type="button" class="hidden h-9 px-3 sm:inline-flex" disabled={loggingOut} on:click={signOut}>
            {loggingOut ? 'Logging out...' : 'Logout'}
          </Button>
          <button
            class="rounded-lg border border-border bg-white p-2 text-muted-foreground shadow-sm hover:bg-primary-50 hover:text-primary md:hidden"
            type="button"
            aria-label="Toggle navigation"
            on:click={() => (mobileNavOpen = !mobileNavOpen)}
          >
            =
          </button>
        {:else}
          <a class="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-primary-50 hover:text-primary" href="/login">Login</a>
          <a class="rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary-800" href="/register">Register</a>
        {/if}
      </div>
    </nav>

    {#if $session.authenticated && mobileNavOpen}
      <div class="border-t border-border bg-white px-4 py-3 md:hidden">
        <div class="mx-auto grid max-w-7xl gap-1 text-sm font-medium text-muted-foreground">
          {#each navItems as item}
            <a class="rounded-lg px-3 py-2 hover:bg-primary-50 hover:text-primary" href={item.href} on:click={() => (mobileNavOpen = false)}>
              {item.label}
            </a>
          {/each}
          <button
            class="rounded-lg px-3 py-2 text-left text-destructive hover:bg-destructive/10"
            type="button"
            disabled={loggingOut}
            on:click={signOut}
          >
            {loggingOut ? 'Logging out...' : 'Logout'}
          </button>
        </div>
      </div>
    {/if}
  </header>

  <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <Alert variant="danger" message={logoutError} class="mb-4" />
    <slot />
  </main>
</div>
