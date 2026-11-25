<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { ApiError } from '$lib/api';
  import {
    createTask,
    deleteTask,
    getProject,
    updateProject,
    updateTask,
    type ProjectDetail,
    type Task
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
  let project: ProjectDetail | null = null;
  let loading = false;
  let loadingProjectId: number | null = null;
  let loadRequestToken = 0;
  let loaded = false;
  let loadedProjectId: number | null = null;
  let error = '';
  let errorStatus: number | null = null;
  let metadataError = '';
  let taskError = '';
  let taskDrafts: Record<number, string> = {};
  let projectName = '';
  let projectDescription = '';
  let newTaskTitle = '';
  let savingProject = false;
  let creatingTask = false;
  let savingTaskId: number | null = null;
  let deletingTaskId: number | null = null;

  $: projectId = Number($page.params.id);
  $: invalidProjectId = !Number.isInteger(projectId) || projectId <= 0;

  $: if (loadedProjectId !== null && loadedProjectId !== projectId) {
    project = null;
    loaded = false;
    loadedProjectId = null;
    error = '';
    errorStatus = null;
  }

  $: if (
    sessionChecked &&
    $session.authenticated &&
    !invalidProjectId &&
    !loaded &&
    loadingProjectId !== projectId
  ) {
    loadProject();
  }

  $: if (!$session.authenticated || invalidProjectId) {
    loadRequestToken += 1;
    loading = false;
    loadingProjectId = null;
    project = null;
    loaded = false;
    loadedProjectId = null;
    error = invalidProjectId ? 'Project not found.' : '';
    errorStatus = invalidProjectId ? 404 : null;
  }

  onMount(async () => {
    await ensureSessionLoaded();
    sessionChecked = true;
  });

  async function loadProject() {
    const requestedProjectId = projectId;
    const requestToken = loadRequestToken + 1;

    loadRequestToken = requestToken;
    loading = true;
    loadingProjectId = requestedProjectId;
    error = '';
    errorStatus = null;
    metadataError = '';
    taskError = '';

    try {
      const detail = await getProject(requestedProjectId);

      if (requestToken !== loadRequestToken || requestedProjectId !== projectId) {
        return;
      }

      project = detail;
      projectName = detail.name;
      projectDescription = detail.description ?? '';
      taskDrafts = Object.fromEntries(detail.tasks.map((task) => [task.id, task.title]));
      loaded = true;
      loadedProjectId = requestedProjectId;
    } catch (caught) {
      if (requestToken !== loadRequestToken || requestedProjectId !== projectId) {
        return;
      }

      error = caught instanceof ApiError ? caught.message : 'Project could not be loaded.';
      errorStatus = caught instanceof ApiError ? caught.status : null;
      project = null;
      loaded = true;
      loadedProjectId = requestedProjectId;
    } finally {
      if (requestToken === loadRequestToken && requestedProjectId === projectId) {
        loading = false;
        loadingProjectId = null;
      }
    }
  }

  async function saveProject() {
    if (!project) {
      return;
    }

    savingProject = true;
    metadataError = '';

    try {
      await updateProject(project.id, {
        name: projectName,
        description: projectDescription.trim() === '' ? null : projectDescription
      });
      loaded = false;
      await loadProject();
    } catch (caught) {
      metadataError = caught instanceof ApiError ? caught.message : 'Project could not be updated.';
    } finally {
      savingProject = false;
    }
  }

  async function submitTask() {
    if (!project) {
      return;
    }

    creatingTask = true;
    taskError = '';

    try {
      await createTask(project.id, { title: newTaskTitle });
      newTaskTitle = '';
      loaded = false;
      await loadProject();
    } catch (caught) {
      taskError = caught instanceof ApiError ? caught.message : 'Task could not be created.';
    } finally {
      creatingTask = false;
    }
  }

  async function toggleTask(task: Task, event: Event) {
    const completed = (event.currentTarget as HTMLInputElement).checked;
    await saveTask(task, { completed });
  }

  async function saveTaskTitle(task: Task) {
    await saveTask(task, { title: taskDrafts[task.id] ?? '' });
  }

  async function saveTask(task: Task, input: { title?: string; completed?: boolean }) {
    savingTaskId = task.id;
    taskError = '';

    try {
      await updateTask(task.id, input);
      loaded = false;
      await loadProject();
    } catch (caught) {
      taskError = caught instanceof ApiError ? caught.message : 'Task could not be updated.';
    } finally {
      savingTaskId = null;
    }
  }

  async function removeTask(task: Task) {
    if (!window.confirm(`Delete "${task.title}"?`)) {
      return;
    }

    deletingTaskId = task.id;
    taskError = '';

    try {
      await deleteTask(task.id);
      loaded = false;
      await loadProject();
    } catch (caught) {
      taskError = caught instanceof ApiError ? caught.message : 'Task could not be deleted.';
    } finally {
      deletingTaskId = null;
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
      <a class="text-sm font-medium text-primary hover:underline" href="/projects">Back to projects</a>
      <h1 class="break-words text-2xl font-semibold">{project?.name ?? 'Project'}</h1>
      <p class="text-sm text-muted-foreground">Edit project details and manage tasks.</p>
    </div>
  </div>

  {#if $sessionLoading}
    <LoadingState label="Loading session..." />
  {:else if !$session.authenticated}
    <EmptyState title="Not signed in" description="Sign in to manage this project.">
      <a class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90" href="/login">
        Go to login
      </a>
    </EmptyState>
  {:else if invalidProjectId}
    <EmptyState title="Project not found" description="The project URL does not include a valid project id.">
      <a class="inline-flex h-10 items-center justify-center rounded-md border border-border bg-white px-4 text-sm font-medium text-foreground shadow-sm hover:bg-muted" href="/projects">
        View projects
      </a>
    </EmptyState>
  {:else if loading && !loaded}
    <LoadingState label="Loading project..." />
  {:else if error}
    <EmptyState title={errorStatus === 404 ? 'Project not found' : 'Project unavailable'} description={error}>
      <a class="inline-flex h-10 items-center justify-center rounded-md border border-border bg-white px-4 text-sm font-medium text-foreground shadow-sm hover:bg-muted" href="/projects">
        View projects
      </a>
    </EmptyState>
  {:else if project}
    <div class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
      <div class="grid content-start gap-6">
        <Card class="p-5">
          <form class="grid gap-4" on:submit|preventDefault={saveProject}>
            <div class="grid gap-1">
              <h2 class="text-base font-semibold">Project metadata</h2>
              <p class="text-sm text-muted-foreground">Keep the project name concise for navigation.</p>
            </div>

            <FormField label="Name">
              <Input bind:value={projectName} maxlength="120" required />
            </FormField>

            <FormField label="Description">
              <textarea
                bind:value={projectDescription}
                class="min-h-24 w-full resize-y rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground shadow-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20 disabled:cursor-not-allowed disabled:opacity-50"
                rows="4"
              ></textarea>
            </FormField>

            <ErrorMessage message={metadataError} />

            <Button type="submit" class="w-full" disabled={savingProject}>
              {savingProject ? 'Saving...' : 'Save project'}
            </Button>
          </form>
        </Card>

        <Card class="grid gap-3 p-5">
          <h2 class="text-base font-semibold">Project details</h2>
          <dl class="grid gap-3 text-sm">
            <div class="grid gap-1">
              <dt class="text-muted-foreground">Created</dt>
              <dd class="font-medium">{formatDate(project.createdAt)}</dd>
            </div>
            <div class="grid gap-1">
              <dt class="text-muted-foreground">Updated</dt>
              <dd class="font-medium">{formatDate(project.updatedAt)}</dd>
            </div>
            <div class="grid gap-1">
              <dt class="text-muted-foreground">Tasks</dt>
              <dd class="font-medium">
                {project.tasks.filter((task) => !task.completed).length} open,
                {project.tasks.filter((task) => task.completed).length} completed
              </dd>
            </div>
          </dl>
        </Card>
      </div>

      <div class="grid min-w-0 content-start gap-4">
        <Card class="p-5">
          <form class="grid gap-4" on:submit|preventDefault={submitTask}>
            <div class="grid gap-1">
              <h2 class="text-base font-semibold">New task</h2>
              <p class="text-sm text-muted-foreground">Add a task to this project.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
              <FormField label="Title">
                <Input bind:value={newTaskTitle} maxlength="180" required />
              </FormField>
              <Button type="submit" class="w-full sm:w-auto" disabled={creatingTask}>
                {creatingTask ? 'Adding...' : 'Add task'}
              </Button>
            </div>
          </form>
        </Card>

        <ErrorMessage message={taskError} />

        {#if project.tasks.length === 0}
          <EmptyState title="No tasks" description="Add the first task for this project." />
        {:else}
          <Card class="overflow-hidden">
            <div class="divide-y divide-border">
              {#each project.tasks as task}
                <div class="grid gap-3 p-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                  <label class="flex items-center gap-3 text-sm font-medium">
                    <input
                      class="h-4 w-4 rounded border-input text-primary focus:ring-ring"
                      type="checkbox"
                      checked={task.completed}
                      disabled={savingTaskId === task.id}
                      on:change={(event) => toggleTask(task, event)}
                    />
                    <span class="sr-only">Complete task</span>
                  </label>

                  <div class="grid min-w-0 gap-2">
                    <Input
                      bind:value={taskDrafts[task.id]}
                      class={task.completed ? 'line-through decoration-2' : ''}
                      maxlength="180"
                      aria-label="Task title"
                      disabled={savingTaskId === task.id || deletingTaskId === task.id}
                    />
                    <p class="text-xs text-muted-foreground">Updated {formatDate(task.updatedAt)}</p>
                  </div>

                  <div class="flex flex-wrap gap-2 sm:justify-end">
                    <Button
                      variant="secondary"
                      class="h-9 px-3"
                      disabled={savingTaskId === task.id || deletingTaskId === task.id}
                      on:click={() => saveTaskTitle(task)}
                    >
                      {savingTaskId === task.id ? 'Saving...' : 'Save'}
                    </Button>
                    <Button
                      variant="danger"
                      class="h-9 px-3"
                      disabled={deletingTaskId === task.id || savingTaskId === task.id}
                      on:click={() => removeTask(task)}
                    >
                      {deletingTaskId === task.id ? 'Deleting...' : 'Delete'}
                    </Button>
                  </div>
                </div>
              {/each}
            </div>
          </Card>
        {/if}
      </div>
    </div>
  {/if}
</div>
