<script lang="ts">
  import { onMount } from 'svelte';
  import { ApiError } from '$lib/api';
  import { getProject, listProjects, type ProjectDetail } from '$lib/domain/projects';
  import { ensureSessionLoaded, session, sessionLoading } from '$lib/session';
  import Card from '$lib/components/ui/Card.svelte';
  import EmptyState from '$lib/components/ui/EmptyState.svelte';
  import ErrorMessage from '$lib/components/ui/ErrorMessage.svelte';
  import LoadingState from '$lib/components/ui/LoadingState.svelte';

  let sessionChecked = false;
  let projectsLoading = false;
  let projectsLoaded = false;
  let projectsError = '';
  let projects: ProjectDetail[] = [];

  $: if (sessionChecked && $session.authenticated && !projectsLoaded && !projectsLoading) {
    loadProjectSummary();
  }

  $: if (!$session.authenticated) {
    projects = [];
    projectsLoaded = false;
    projectsError = '';
  }

  $: totalProjects = projects.length;
  $: openTasks = projects.reduce(
    (total, project) => total + project.tasks.filter((task) => !task.completed).length,
    0
  );
  $: completedTasks = projects.reduce(
    (total, project) => total + project.tasks.filter((task) => task.completed).length,
    0
  );
  $: recentProjects = projects.slice(0, 4);

  onMount(async () => {
    await ensureSessionLoaded();
    sessionChecked = true;
  });

  async function loadProjectSummary() {
    projectsLoading = true;
    projectsError = '';

    try {
      const listedProjects = await listProjects();
      projects = await Promise.all(listedProjects.map((project) => getProject(project.id)));
      projectsLoaded = true;
    } catch (caught) {
      projectsError = caught instanceof ApiError ? caught.message : 'Projects could not be loaded.';
      projectsLoaded = true;
    } finally {
      projectsLoading = false;
    }
  }

  function formatDate(value: string): string {
    const normalized = value.includes('T') ? value : `${value.replace(' ', 'T')}Z`;
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short'
    }).format(date);
  }
</script>

<div class="grid gap-6">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="grid gap-1">
      <h1 class="text-2xl font-semibold">Dashboard</h1>
      <p class="text-sm text-muted-foreground">Account status and current project workload.</p>
    </div>
  </div>

  {#if $sessionLoading}
    <LoadingState label="Loading session..." />
  {:else if !$session.authenticated}
    <EmptyState title="Not signed in" description="Sign in to open your dashboard.">
      <a class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90" href="/login">
        Go to login
      </a>
    </EmptyState>
  {:else if projectsLoading && !projectsLoaded}
    <LoadingState label="Loading projects..." />
  {:else}
    <ErrorMessage message={projectsError} />

    {#if !projectsError}
      <div class="grid gap-4 sm:grid-cols-3">
        <Card class="grid gap-2 p-5">
          <span class="text-sm font-medium text-muted-foreground">Total projects</span>
          <strong class="text-3xl font-semibold">{totalProjects}</strong>
        </Card>

        <Card class="grid gap-2 p-5">
          <span class="text-sm font-medium text-muted-foreground">Open tasks</span>
          <strong class="text-3xl font-semibold">{openTasks}</strong>
        </Card>

        <Card class="grid gap-2 p-5">
          <span class="text-sm font-medium text-muted-foreground">Completed tasks</span>
          <strong class="text-3xl font-semibold">{completedTasks}</strong>
        </Card>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <Card class="grid gap-4 p-5">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="grid gap-1">
              <h2 class="text-base font-semibold">Recent projects</h2>
              <p class="text-sm text-muted-foreground">Projects ordered by latest API update.</p>
            </div>
            <a class="text-sm font-medium text-primary hover:underline" href="/projects">View all</a>
          </div>

          {#if recentProjects.length === 0}
            <div class="rounded-md border border-dashed border-border px-4 py-6 text-sm text-muted-foreground">
              No projects yet.
            </div>
          {:else}
            <div class="overflow-x-auto">
              <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="text-xs uppercase text-muted-foreground">
                  <tr class="border-b border-border">
                    <th class="py-2 pr-4 font-medium">Project</th>
                    <th class="py-2 pr-4 font-medium">Open</th>
                    <th class="py-2 pr-4 font-medium">Completed</th>
                    <th class="py-2 font-medium">Updated</th>
                  </tr>
                </thead>
                <tbody>
                  {#each recentProjects as project}
                    <tr class="border-b border-border last:border-0">
                      <td class="py-3 pr-4">
                        <a class="font-medium text-primary hover:underline" href={`/projects/${project.id}`}>
                          {project.name}
                        </a>
                      </td>
                      <td class="py-3 pr-4">{project.tasks.filter((task) => !task.completed).length}</td>
                      <td class="py-3 pr-4">{project.tasks.filter((task) => task.completed).length}</td>
                      <td class="py-3 text-muted-foreground">{formatDate(project.updatedAt)}</td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </Card>

        <Card class="grid gap-3 p-5">
          <h2 class="text-base font-semibold">Profile</h2>
          <dl class="grid gap-3 text-sm">
            <div class="grid gap-1">
              <dt class="text-muted-foreground">Email</dt>
              <dd class="break-words font-medium">{$session.email}</dd>
            </div>
            <div class="grid gap-1">
              <dt class="text-muted-foreground">Display name</dt>
              <dd class="font-medium">{$session.nickname || 'Not set'}</dd>
            </div>
          </dl>
        </Card>
      </div>
    {/if}
  {/if}
</div>
