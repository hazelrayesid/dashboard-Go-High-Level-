<?php

namespace App\Http\Controllers;

use App\Services\GhlEmailWorkflowSelectionRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GhlEmailWorkflowSelectionController extends Controller
{
    public function __invoke(Request $request, GhlEmailWorkflowSelectionRepository $workflows): RedirectResponse
    {
        $validated = $request->validate([
            'workflow_ids' => ['array'],
            'workflow_ids.*' => ['string'],
        ]);

        $workflows->saveSelection($validated['workflow_ids'] ?? []);

        return redirect()
            ->route('dashboard', $request->except(['_token', 'workflow_ids']))
            ->with('ghl_email_workflows_status', 'Workflow campaign selection updated.');
    }
}
