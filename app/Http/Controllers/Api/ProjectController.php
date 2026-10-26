<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->projects()->withCount('tasks')->get();
    }

    public function destroy(Project $project)
    {
        Gate::authorize('delete', $project);
        $project->delete();

        return response()->noContent();
    }

    public function showTask(Project $project, Task $task)
    {
        Gate::authorize('view', $project);

        return $task;
    }

    public function updateTask(Request $request, Project $project, Task $task)
    {
        Gate::authorize('update', $project);
        $task->update($request->validate(['title' => 'sometimes|string|max:255', 'done' => 'sometimes|boolean']));

        return $task;
    }
}
