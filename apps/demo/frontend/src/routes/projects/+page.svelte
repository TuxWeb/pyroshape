<script lang="ts">
  import { onMount } from 'svelte';
  import { ApiError } from '#lib/api.ts';
  import {
    createProject,
    deleteProject,
    listProjects,
    type Project
  } from '#lib/domain/projects.ts';
  import { ensureSessionLoaded, session, sessionLoading } from '#lib/session.ts';
  import Alert from '#lib/components/ui/Alert.svelte';
  import Badge from '#lib/components/ui/Badge.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Card from '#lib/components/ui/Card.svelte';
  import EmptyState from '#lib/components/ui/EmptyState.svelte';
  import ErrorMessage from '#lib/components/ui/ErrorMessage.svelte';
  import FormField from '#lib/components/ui/FormField.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import LoadingState from '#lib/components/ui/LoadingState.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import Table from '#lib/components/ui/Table.svelte';
  import Textarea from '#lib/components/ui/Textarea.svelte';

  let sessionChecked = false;
  let projects: Project[] = [];
  let loading = false;
  let loaded = false;
  let error = '';
  let formError = '';
  let deletingId: number | null = null;
  let projectToDelete: Project | null = null;
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
    projectToDelete = null;
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

  async function confirmDeleteProject() {
    if (!projectToDelete) {
      return;
    }

    deletingId = projectToDelete.id;
    error = '';

    try {
      await deleteProject(projectToDelete.id);
      projectToDelete = null;
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
    {#if $session.authenticated}
      <Badge variant="blue">{projects.length} total</Badge>
    {/if}
  </div>

  {#if $sessionLoading}
    <LoadingState label="Loading session..." />
  {:else if !$session.authenticated}
    <EmptyState title="Not signed in" description="Sign in to manage projects.">
      <a class="inline-flex h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90" href="/login">
        Go to login
      </a>
    </EmptyState>
  {:else}
    <div class="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
      <Card class="p-5">
        <form class="grid gap-4" on:submit|preventDefault={submitProject}>
          <div class="grid gap-1">
            <h2 class="text-base font-semibold">New project</h2>
            <p class="text-sm text-muted-foreground">Name the workspace before adding tasks.</p>
          </div>

          <FormField label="Name">
            <Input bind:value={name} maxlength="120" required placeholder="Website refresh" />
          </FormField>

          <FormField label="Description">
            <Textarea bind:value={description} rows={4} placeholder="Optional project notes" />
          </FormField>

          <ErrorMessage message={formError} />

          <Button type="submit" class="w-full" disabled={submitting}>
            {submitting ? 'Creating...' : 'Create project'}
          </Button>
        </form>
      </Card>

      <div class="grid min-w-0 content-start gap-4">
        <Alert variant="danger" message={error} />

        {#if loading && !loaded}
          <LoadingState label="Loading projects..." />
        {:else if !error && projects.length === 0}
          <EmptyState title="No projects" description="Create your first project to start tracking tasks." />
        {:else if !error}
          <Table>
            <thead class="bg-muted/70 text-xs uppercase text-muted-foreground">
              <tr>
                <th class="px-4 py-3 font-medium">Project</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium">Created</th>
                <th class="px-4 py-3 font-medium">Updated</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              {#each projects as project}
                <tr class="hover:bg-muted/40">
                  <td class="max-w-[18rem] px-4 py-3">
                    <a class="block truncate font-medium text-primary hover:underline" href={`/projects/${project.id}`}>
                      {project.name}
                    </a>
                    {#if project.description}
                      <p class="mt-1 truncate text-xs text-muted-foreground">{project.description}</p>
                    {/if}
                  </td>
                  <td class="px-4 py-3">
                    <Badge variant="green">Active</Badge>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{formatDate(project.createdAt)}</td>
                  <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{formatDate(project.updatedAt)}</td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <a
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-foreground shadow-sm hover:bg-muted"
                        href={`/projects/${project.id}`}
                      >
                        Open
                      </a>
                      <Button
                        variant="danger"
                        class="h-9 px-3"
                        disabled={deletingId === project.id}
                        on:click={() => (projectToDelete = project)}
                      >
                        Delete
                      </Button>
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </Table>
        {/if}
      </div>
    </div>
  {/if}
</div>

<Modal
  open={projectToDelete !== null}
  title="Delete project"
  description={projectToDelete ? `This deletes "${projectToDelete.name}" and all of its tasks.` : ''}
  confirmLabel="Delete project"
  confirmVariant="danger"
  confirming={deletingId !== null}
  on:close={() => (projectToDelete = null)}
  on:confirm={confirmDeleteProject}
>
  This action cannot be undone.
</Modal>
