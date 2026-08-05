<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get the first report with task
$report = \App\Models\AnalysisReport::with('task')->first();

echo "Report ID: {$report->id}\n";
echo "Task ID: {$report->task_id}\n";

if ($report->task) {
    echo "Task found:\n";
    echo "- Task No: {$report->task->task_no}\n";
    echo "- Image URLs: " . json_encode($report->task->image_urls) . "\n";
} else {
    echo "Task NOT found!\n";
    
    // Check if task exists
    $task = \App\Models\AnalysisTask::find($report->task_id);
    if ($task) {
        echo "But task exists in DB:\n";
        echo "- Task No: {$task->task_no}\n";
        echo "- Image URLs: " . json_encode($task->image_urls) . "\n";
    } else {
        echo "Task not in DB either!\n";
    }
}

// Check the relation definition
echo "\n=== Relation Check ===\n";
$report = \App\Models\AnalysisReport::first();
echo "task_id value: {$report->task_id}\n";
echo "task relation exists: " . ($report->task ? 'yes' : 'no') . "\n";

// Check the AnalysisTask table
echo "\n=== AnalysisTask Table ===\n";
$tasks = \App\Models\AnalysisTask::count();
echo "Total tasks: {$tasks}\n";

// Check if task_id in AnalysisReport matches id in AnalysisTask
$reports = \App\Models\AnalysisReport::limit(3)->get();
foreach ($reports as $r) {
    $task = \App\Models\AnalysisTask::find($r->task_id);
    echo "Report #{$r->id}: task_id={$r->task_id}, task=" . ($task ? $task->task_no : 'NOT FOUND') . "\n";
}
