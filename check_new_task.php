<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check the new test task
$taskNo = 'TK20260805d3e5afd7';

$task = \App\Models\AnalysisTask::where('task_no', $taskNo)->first();

if ($task) {
    echo "Task found:\n";
    echo "ID: {$task->id}\n";
    echo "Task No: {$task->task_no}\n";
    echo "User: {$task->user->username}\n";
    echo "Image URL: {$task->image_url}\n";
    echo "Image URLs: " . json_encode($task->image_urls) . "\n";
    echo "Status: {$task->status}\n";
    
    // Check if report exists
    $report = \App\Models\AnalysisReport::where('task_id', $task->id)->first();
    if ($report) {
        echo "\nReport found:\n";
        echo "Report ID: {$report->id}\n";
        echo "Type: {$report->type}\n";
    } else {
        echo "\nNo report generated yet (task may still be pending)\n";
    }
} else {
    echo "Task not found\n";
}
