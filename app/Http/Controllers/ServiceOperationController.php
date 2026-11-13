<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ServiceNoteRequest;
use App\Http\Requests\ServiceOccurrenceRequest;
use App\Http\Requests\ServiceTaskRequest;
use App\Http\Requests\ServiceTaskStatusRequest;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\ServiceTask;
use Illuminate\Http\RedirectResponse;

final class ServiceOperationController extends Controller
{
    public function task(ServiceTaskRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $service->tasks()->create([...$request->validated(), 'tenant_id' => $service->tenant_id]);

        return back()->with('success', 'Tarefa adicionada.');
    }

    public function status(ServiceTaskStatusRequest $request, Service $service, ServiceTask $task): RedirectResponse
    {
        abort_unless((int) $task->service_id === (int) $service->id && (int) $task->tenant_id === (int) $service->tenant_id, 404);
        $this->authorize('updateStatus', $task);
        $status = $request->string('status')->toString();
        $completed = $status === 'completed';
        $task->update(['status' => $status, 'completed_by' => $completed ? $request->user()->id : null, 'completed_at' => $completed ? now() : null]);
        ActivityLog::create(['tenant_id' => $service->tenant_id, 'actor_user_id' => $request->user()->id, 'event' => $completed ? 'service.task_completed' : 'service.task_reopened', 'subject_type' => ServiceTask::class, 'subject_id' => $task->id, 'properties_json' => ['service_id' => $service->id, 'status' => $status]]);

        return back()->with('success', $completed ? 'Tarefa concluída.' : 'Tarefa reaberta.');
    }

    public function note(ServiceNoteRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $service->notes()->create(['tenant_id' => $service->tenant_id, 'author_id' => $request->user()->id, 'body' => $request->string('body')->toString()]);

        return back()->with('success', 'Nota adicionada.');
    }

    public function occurrence(ServiceOccurrenceRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $service->occurrences()->create([...$request->validated(), 'tenant_id' => $service->tenant_id, 'author_id' => $request->user()->id]);

        return back()->with('success', 'Ocorrência registrada.');
    }
}
