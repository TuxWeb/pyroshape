<script lang="ts">
  import { onMount } from 'svelte';
  import { ApiError } from '$lib/api';
  import {
    createProject,
    deleteProject,
    listProjects,
    type Project
  } from '$lib/domain/projects';
  import { ensureSessionLoaded, session, sessionLoading } from '$lib/session';
  import Button from '$lib/components/ui/Button.svelte';
  import Card from '$lib/components/ui/Card.svelte';
  import EmptyState from '$lib/components/ui/EmptyState.svelte';
  import ErrorMessage from '$lib/components/ui/ErrorMessage.svelte';
  import FormField from '$lib/components/ui/FormField.svelte';
  import Input from '$lib/components/ui/Input.svelte';
  import LoadingState from '$lib/components/ui/LoadingState.svelte';

  let sessionChecked = false;
  let projects: Project[] = [];
  let loading = false;
  let loaded = false;
  let error = '';
  let formError = '';
  let deletingId: number | null = null;
  let submitting = false;
  let name = '';
  let description = '';

  $: if (sessionChecked && $session.authenticated && !loaded && !loading) {
    loadProjects();
  }

  $: if (!$session.authenticated) {
    projects = [];
    loaded = false;
    error = '';
    formError = '';
  }

  onMount(async () => {
    await ensureSessionLoaded();
    sessionChecked = true;
  });

  async function loadProjects() {
    loading = true;
    error = '';

    try {
      projects = await listProjects();
      loaded = true;
    } catch (caught) {
      error = caught instanceof ApiError ? caught.message : 'Projects could not be loaded.';
      loaded = true;
    } finally {
      loading = false;
    }
  }

  async function submitProject() {
    formError = '';
    submitting = true;

    try {
      await createProject({
        name,
        description: description.trim() === '' ? null : description
      });
      name = '';
      description = '';
      loaded = false;
      await loadProjects();
    } catch (caught) {
      formError = caught instanceof ApiError ? caught.message : 'Project could not be created.';
    } finally {
      submitting = false;
    }
  }

  async function removeProject(project: Project) {
    if (!window.confirm(`Delete "${project.name}"? This also deletes its tasks.`)) {
      return;
    }

    deletingId = project.id;
    error = '';

    try {
      await deleteProject(project.id);
      loaded = false;
      await loadProjects();
    } catch (caught) {
      error = caught instanceof ApiError ? caught.message : 'Project could not be deleted.';
    } finally {
      deletingId = null;
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
      <h1 class="text-2xl font-semibold">Projects</h1>
      <p class="text-sm text-muted-foreground">Create projects and open task workspaces.</p>
    </div>
  </div>

  {#if $sessionLoading}
    <LoadingState label="Loading session..." />
  {:else if !$session.authenticated}
    <EmptyState title="Not signed in" description="Sign in to manage projects.">
      <a class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90" href="/login">
        Go to login
      </a>
    </EmptyState>
  {:else}
    <div class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
      <Card class="p-5">
        <form class="grid gap-4" on:submit|preventDefault={submitProject}>
          <div class="grid gap-1">
            <h2 class="text-base font-semibold">New project</h2>
            <p class="text-sm text-muted-foreground">Name the workspace before adding tasks.</p>
          </div>

          <FormField label="Name">
            <Input bind:value={name} maxlength="120" required />
          </FormField>

          <FormField label="Description">
            <textarea
              bind:value={description}
              class="min-h-24 w-full resize-y rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground shadow-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20 disabled:cursor-not-allowed disabled:opacity-50"
              rows="4"
            ></textarea>
          </FormField>

          <ErrorMessage message={formError} />

          <Button type="submit" class="w-full" disabled={submitting}>
            {submitting ? 'Creating...' : 'Create project'}
          </Button>
        </form>
      </Card>

      <div class="grid min-w-0 gap-4">
        <ErrorMessage message={error} />

        {#if loading && !loaded}
          <LoadingState label="Loading projects..." />
        {:else if !error && projects.length === 0}
          <EmptyState title="No projects" description="Create your first project to start tracking tasks." />
        {:else if !error}
          <Card class="overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full min-w-[42rem] text-left text-sm">
                <thead class="bg-muted/70 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th class="px-4 py-3 font-medium">Project</th>
                    <th class="px-4 py-3 font-medium">Created</th>
                    <th class="px-4 py-3 font-medium">Updated</th>
                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {#each projects as project}
                    <tr class="border-t border-border">
                      <td class="max-w-[18rem] px-4 py-3">
                        <a class="block truncate font-medium text-primary hover:underline" href={`/projects/${project.id}`}>
                          {project.name}
                        </a>
                        {#if project.description}
                          <p class="mt-1 truncate text-xs text-muted-foreground">{project.description}</p>
                        {/if}
                      </td>
                      <td class="px-4 py-3 text-muted-foreground">{formatDate(project.createdAt)}</td>
                      <td class="px-4 py-3 text-muted-foreground">{formatDate(project.updatedAt)}</td>
                      <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                          <a
                            class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-white px-3 text-sm font-medium text-foreground shadow-sm hover:bg-muted"
                            href={`/projects/${project.id}`}
                          >
                            Open
                          </a>
                          <Button
                            variant="danger"
                            class="h-9 px-3"
                            disabled={deletingId === project.id}
                            on:click={() => removeProject(project)}
                          >
                            {deletingId === project.id ? 'Deleting...' : 'Delete'}
                          </Button>
                        </div>
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          </Card>
        {/if}
      </div>
    </div>
  {/if}
</div>
