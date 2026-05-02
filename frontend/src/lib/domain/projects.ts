import { apiJson } from '$lib/api';

export type Task = {
  id: number;
  projectId: number;
  title: string;
  completed: boolean;
  position: number;
  createdAt: string;
  updatedAt: string;
};

export type Project = {
  id: number;
  name: string;
  description?: string | null;
  createdAt: string;
  updatedAt: string;
};

export type ProjectDetail = Project & {
  tasks: Task[];
};

type ProjectInput = {
  name: string;
  description?: string | null;
};

type TaskInput = {
  title?: string;
  completed?: boolean;
  position?: number;
};

export async function listProjects(): Promise<Project[]> {
  const response = await apiJson<{ projects: Project[] }>('/api/projects');

  return response.projects;
}

export async function createProject(input: ProjectInput): Promise<Project> {
  const response = await apiJson<{ project: Project }>('/api/projects', {
    method: 'POST',
    body: JSON.stringify(input)
  });

  return response.project;
}

export async function getProject(id: number): Promise<ProjectDetail> {
  const response = await apiJson<{ project: ProjectDetail }>(`/api/projects/${id}`);

  return response.project;
}

export async function updateProject(id: number, input: ProjectInput): Promise<ProjectDetail> {
  const response = await apiJson<{ project: ProjectDetail }>(`/api/projects/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input)
  });

  return response.project;
}

export async function deleteProject(id: number): Promise<void> {
  await apiJson<{ deleted: true }>(`/api/projects/${id}`, { method: 'DELETE' });
}

export async function createTask(projectId: number, input: { title: string }): Promise<Task> {
  const response = await apiJson<{ task: Task }>(`/api/projects/${projectId}/tasks`, {
    method: 'POST',
    body: JSON.stringify(input)
  });

  return response.task;
}

export async function updateTask(id: number, input: TaskInput): Promise<Task> {
  const response = await apiJson<{ task: Task }>(`/api/tasks/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input)
  });

  return response.task;
}

export async function deleteTask(id: number): Promise<void> {
  await apiJson<{ deleted: true }>(`/api/tasks/${id}`, { method: 'DELETE' });
}
